<?php

namespace App\Actions\Games;

use App\Models\Game;

class RecordGameDownload
{
    /**
     * Increment the game download counter when a user continues to an external link.
     *
     * Counters must not bump updated_at — sitemap lastmod and "recently edited"
     * signals should reflect content changes, not download traffic.
     */
    public function __invoke(Game $game): void
    {
        Game::withoutTimestamps(function () use ($game): void {
            $game->increment('downloads_count');
        });
    }
}
