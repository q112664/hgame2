<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Database\Eloquent\Builder;
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
            $like = '%'.addcslashes($term, '%_\\').'%';

            $games->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('developer', 'like', $like)
                    ->orWhereHas(
                        'category',
                        fn (Builder $category): Builder => $category
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like),
                    )
                    ->orWhereHas(
                        'tags',
                        fn (Builder $tags): Builder => $tags->where('name', 'like', $like),
                    )
                    ->orWhereHas(
                        'releases.platforms',
                        fn (Builder $platforms): Builder => $platforms
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like),
                    )
                    ->orWhereHas(
                        'releases.languages',
                        fn (Builder $languages): Builder => $languages
                            ->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like),
                    );
            });
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
