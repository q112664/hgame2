<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Support\Collection;

class ListRelatedGames
{
    public const int DefaultLimit = 4;

    /**
     * Simple “more recommendations”: same category first, then popular.
     *
     * @return list<array<string, mixed>>
     */
    public function __invoke(Game $game, int $limit = self::DefaultLimit): array
    {
        if ($limit < 1) {
            return [];
        }

        $query = Game::query()
            ->published()
            ->withCardData()
            ->whereKeyNot($game->getKey());

        if ($game->category_id !== null) {
            $query->orderByRaw('case when category_id = ? then 0 else 1 end', [$game->category_id]);
        }

        $games = $query
            ->orderByDesc('views_count')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        return $this->present($games);
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return list<array<string, mixed>>
     */
    private function present(Collection $games): array
    {
        return $games
            ->map(fn (Game $item): array => GamePresenter::card($item))
            ->values()
            ->all();
    }
}
