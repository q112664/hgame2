<?php

namespace App\Actions\Games;

use App\GameMetric;
use App\Models\Game;
use App\Models\GameRelease;

class RecordGameDownload
{
    public function __construct(
        private RecordGameDailyStat $recordGameDailyStat,
    ) {}

    /**
     * Increment the counters when a user continues to an external link: one for
     * the package the link belongs to, one for the game as a whole.
     *
     * Counters must not bump updated_at — sitemap lastmod and "recently edited"
     * signals should reflect content changes, not download traffic. That is why
     * each increment runs inside its own class's withoutTimestamps scope.
     */
    public function __invoke(Game $game, GameRelease $release): void
    {
        GameRelease::withoutTimestamps(function () use ($release): void {
            $release->increment('downloads_count');
        });

        Game::withoutTimestamps(function () use ($game): void {
            $game->increment('downloads_count');
        });

        ($this->recordGameDailyStat)($game, GameMetric::Downloads);
    }
}
