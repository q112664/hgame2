<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchGames
{
    /**
     * @return Collection<int, array{id: string, title: string, subtitle: string|null, thumbnail: string}>
     */
    public function __invoke(?string $query, int $limit = 50): Collection
    {
        $term = Str::of($query ?? '')->trim()->toString();

        if ($term === '') {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Game::query()
            ->published()
            ->where(function (Builder $builder) use ($like): void {
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
            })
            ->latest('published_at')
            ->limit($limit)
            ->get(['slug', 'title', 'subtitle', 'cover_url', 'cover_path'])
            ->map(GamePresenter::search(...))
            ->values();
    }
}
