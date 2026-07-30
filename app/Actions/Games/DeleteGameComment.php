<?php

namespace App\Actions\Games;

use App\Models\GameComment;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeleteGameComment
{
    public function __invoke(GameComment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            $commentIds = $this->descendantIds($comment);

            DatabaseNotification::query()
                ->where('type', 'comment.replied')
                ->whereIn('data->comment_id', $commentIds->all())
                ->delete();

            $comment->delete();
        });
    }

    /**
     * Collect the comment and any descendants before the foreign-key cascade.
     *
     * @return Collection<int, int>
     */
    private function descendantIds(GameComment $comment): Collection
    {
        $allIds = collect([(int) $comment->getKey()]);
        $pendingIds = $allIds;

        while ($pendingIds->isNotEmpty()) {
            $childIds = GameComment::query()
                ->whereIn('parent_id', $pendingIds->all())
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->diff($allIds)
                ->values();

            if ($childIds->isEmpty()) {
                break;
            }

            $allIds = $allIds->merge($childIds)->values();
            $pendingIds = $childIds;
        }

        return $allIds;
    }
}
