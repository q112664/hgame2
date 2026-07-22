<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SearchGames
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function __invoke(?string $query, int $perPage = 8): LengthAwarePaginator
    {
        $term = Str::of($query ?? '')->trim()->toString();
        $games = Game::query()
            ->published()
            ->withCardData();

        if ($term === '') {
            $games->whereKey([]);
        } else {
            $games->matchingSearch($term);
        }

        return $games
            ->latest('published_at')
            ->paginate(
                perPage: $perPage,
                columns: [
                    'id',
                    'category_id',
                    'slug',
                    'title',
                    'subtitle',
                    'developer',
                    'cover_url',
                    'cover_path',
                    'published_at',
                    'views_count',
                ],
            )
            ->withQueryString()
            ->through(fn (Game $game): array => GamePresenter::card($game));
    }
}
