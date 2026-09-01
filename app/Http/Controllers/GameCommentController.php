<?php

namespace App\Http\Controllers;

use App\Actions\Games\DeleteGameComment;
use App\Actions\Games\RecalculateGameRatings;
use App\Http\Requests\StoreGameCommentRequest;
use App\Http\Requests\UpdateGameCommentRequest;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\User;
use App\Notifications\CommentRepliedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GameCommentController extends Controller
{
    public function store(
        StoreGameCommentRequest $request,
        Game $resource,
        RecalculateGameRatings $recalculateGameRatings,
    ): RedirectResponse {
        $parentId = $request->validated('parent_id');
        $resolvedParentId = null;
        $replyToUserId = null;
        $rating = $request->validated('rating');

        if ($parentId !== null) {
            $parent = GameComment::query()
                ->whereKey($parentId)
                ->where('game_id', $resource->id)
                ->firstOrFail();

            // One-level 楼中楼: hang under the root; @ the user being replied to.
            $resolvedParentId = $parent->parent_id ?? $parent->id;
            $replyToUserId = $parent->user_id;
            $rating = null;
        }

        $comment = DB::transaction(function () use (
            $request,
            $resource,
            $resolvedParentId,
            $replyToUserId,
            $rating,
        ): GameComment {
            if ($resolvedParentId === null && $rating !== null) {
                // One active star rating per user per game.
                GameComment::query()
                    ->where('game_id', $resource->id)
                    ->where('user_id', $request->user()->id)
                    ->whereNull('parent_id')
                    ->whereNotNull('rating')
                    ->update(['rating' => null]);
            }

            return $resource->comments()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $resolvedParentId,
                'reply_to_user_id' => $replyToUserId,
                'body' => $request->validated('body'),
                'rating' => $rating,
            ]);
        });

        if ($resolvedParentId === null) {
            $recalculateGameRatings($resource);
        }

        if (
            $replyToUserId !== null
            && $replyToUserId !== $request->user()->id
        ) {
            $recipient = User::query()->find($replyToUserId);

            $recipient?->notify(new CommentRepliedNotification($comment));
        }

        Inertia::flash([
            'toast' => [
                'type' => 'success',
                'message' => $resolvedParentId !== null
                    ? __('Reply posted.')
                    : ($rating !== null
                        ? __('Review posted.')
                        : __('Comment posted.')),
            ],
            'createdCommentId' => $comment->id,
        ]);

        return back();
    }

    public function update(
        UpdateGameCommentRequest $request,
        Game $resource,
        GameComment $comment,
        RecalculateGameRatings $recalculateGameRatings,
    ): RedirectResponse {
        abort_unless($comment->game_id === $resource->id, 404);

        $rating = $comment->parent_id === null
            ? $request->validated('rating')
            : null;

        DB::transaction(function () use ($request, $resource, $comment, $rating): void {
            if ($comment->parent_id === null && $rating !== null) {
                GameComment::query()
                    ->where('game_id', $resource->id)
                    ->where('user_id', $request->user()->id)
                    ->whereNull('parent_id')
                    ->whereNotNull('rating')
                    ->whereKeyNot($comment->id)
                    ->update(['rating' => null]);
            }

            $comment->update([
                'body' => $request->validated('body'),
                'rating' => $comment->parent_id === null ? $rating : null,
            ]);
        });

        if ($comment->parent_id === null) {
            $recalculateGameRatings($resource);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Comment updated.'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        Game $resource,
        GameComment $comment,
        DeleteGameComment $deleteGameComment,
        RecalculateGameRatings $recalculateGameRatings,
    ): RedirectResponse {
        abort_unless($comment->game_id === $resource->id, 404);

        $user = $request->user();

        abort_unless(
            $user !== null && ($user->id === $comment->user_id || $user->is_admin),
            403,
        );

        $wasRoot = $comment->parent_id === null;

        $deleteGameComment($comment);

        if ($wasRoot) {
            $recalculateGameRatings($resource);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Comment deleted.'),
        ]);

        return back();
    }
}
