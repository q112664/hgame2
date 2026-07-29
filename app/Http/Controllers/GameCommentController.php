<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameCommentRequest;
use App\Http\Requests\UpdateGameCommentRequest;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\User;
use App\Notifications\CommentRepliedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameCommentController extends Controller
{
    public function store(StoreGameCommentRequest $request, Game $resource): RedirectResponse
    {
        $parentId = $request->validated('parent_id');
        $resolvedParentId = null;
        $replyToUserId = null;

        if ($parentId !== null) {
            $parent = GameComment::query()
                ->whereKey($parentId)
                ->where('game_id', $resource->id)
                ->firstOrFail();

            // One-level 楼中楼: hang under the root; @ the user being replied to.
            $resolvedParentId = $parent->parent_id ?? $parent->id;
            $replyToUserId = $parent->user_id;
        }

        $comment = $resource->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $resolvedParentId,
            'reply_to_user_id' => $replyToUserId,
            'body' => $request->validated('body'),
        ]);

        if (
            $replyToUserId !== null
            && $replyToUserId !== $request->user()->id
        ) {
            $recipient = User::query()->find($replyToUserId);

            $recipient?->notify(new CommentRepliedNotification($comment));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $resolvedParentId !== null
                ? __('Reply posted.')
                : __('Comment posted.'),
        ]);

        return back();
    }

    public function update(
        UpdateGameCommentRequest $request,
        Game $resource,
        GameComment $comment,
    ): RedirectResponse {
        abort_unless($comment->game_id === $resource->id, 404);

        $comment->update([
            'body' => $request->validated('body'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Comment updated.'),
        ]);

        return back();
    }

    public function destroy(Request $request, Game $resource, GameComment $comment): RedirectResponse
    {
        abort_unless($comment->game_id === $resource->id, 404);

        $user = $request->user();

        abort_unless(
            $user !== null && ($user->id === $comment->user_id || $user->is_admin),
            403,
        );

        $comment->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Comment deleted.'),
        ]);

        return back();
    }
}
