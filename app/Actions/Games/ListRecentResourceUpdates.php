<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GamePresenter;

class ListRecentResourceUpdates
{
    public const LIMIT = 8;

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(int $limit = self::LIMIT): array
    {
        $updates = Game::query()
            ->select([
                'id',
                'slug',
                'title',
                'subtitle',
                'developer',
                'cover_url',
                'cover_path',
                'published_at',
                'downloads_updated_at',
            ])
            ->published()
            ->with([
                'releases' => fn ($releases) => $releases->withCardSummary(),
            ])
            ->orderByRaw('COALESCE(downloads_updated_at, published_at) DESC')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Game $game): array => GamePresenter::recentUpdate($game))
            ->all();

        return array_values($updates);
    }
}
