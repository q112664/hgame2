<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GamePresenter;

class ListRecentResourceUpdates
{
    public const LIMIT = 8;

    /**
     * Published games whose downloads were updated (separate from “new listings”).
     *
     * @return list<array<string, mixed>>
     */
    public function __invoke(int $limit = self::LIMIT): array
    {
        $updates = Game::query()
            ->published()
            ->whereNotNull('downloads_updated_at')
            ->withCardData()
            ->orderByDesc('downloads_updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Game $game): array => GamePresenter::card($game))
            ->all();

        return array_values($updates);
    }
}
