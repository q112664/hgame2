<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameComment;

class RecalculateGameRatings
{
    public function __invoke(Game $game): void
    {
        $stats = GameComment::query()
            ->where('game_id', $game->getKey())
            ->whereNull('parent_id')
            ->whereNotNull('rating')
            ->toBase()
            ->selectRaw('COUNT(*) as ratings_count, COALESCE(AVG(rating), 0) as ratings_avg')
            ->first();

        $count = (int) ($stats->ratings_count ?? 0);
        $avg = round((float) ($stats->ratings_avg ?? 0), 2);

        Game::withoutTimestamps(function () use ($game, $count, $avg): void {
            $game->forceFill([
                'ratings_count' => $count,
                'ratings_avg' => $avg,
            ])->saveQuietly();
        });
    }
}
