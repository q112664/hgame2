<?php

namespace App\Actions\Games;

use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use App\Support\GamePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPublishedGames
{
    public const PER_PAGE = 8;

    public const SORT_LATEST = 'latest';

    public const SORT_OLDEST = 'oldest';

    public const SORT_TITLE = 'title';

    public const SORT_VIEWS = 'views';

    /** @var list<string> */
    public const SORTS = [
        self::SORT_LATEST,
        self::SORT_OLDEST,
        self::SORT_TITLE,
        self::SORT_VIEWS,
    ];

    /**
     * @param  array{category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string}  $filters
     * @return array{
     *     resources: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array{category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string},
     *     filterOptions: \Closure(): array{
     *         categories: list<array{name: string, slug: string}>,
     *         platforms: list<array{name: string, slug: string}>,
     *         languages: list<array{name: string, code: string}>,
     *         tags: list<array{name: string, slug: string}>
     *     }
     * }
     */
    public function __invoke(array $filters, int $perPage = self::PER_PAGE): array
    {
        $paginator = $this->applySort($this->query($filters), $filters['sort'])
            ->withCardData()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Game $game): array => GamePresenter::card($game));

        return [
            'resources' => $paginator,
            'filters' => $filters,
            'filterOptions' => fn (): array => $this->filterOptions(),
        ];
    }

    /**
     * @param  array{category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string}  $filters
     * @return Builder<Game>
     */
    private function query(array $filters): Builder
    {
        $query = Game::query()->published();

        if (filled($filters['category'])) {
            $query->whereHas(
                'category',
                fn (Builder $category): Builder => $category->where('slug', $filters['category']),
            );
        }

        if (filled($filters['platform'])) {
            $query->whereHas(
                'releases',
                fn (Builder $releases): Builder => $releases
                    ->where('is_active', true)
                    ->where(function (Builder $published): void {
                        $published
                            ->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->whereHas(
                        'downloadLinks',
                        fn (Builder $links): Builder => $links->where('is_active', true),
                    )
                    ->whereHas(
                        'platforms',
                        fn (Builder $platforms): Builder => $platforms->where('slug', $filters['platform']),
                    ),
            );
        }

        if (filled($filters['language'])) {
            $query->whereHas(
                'releases',
                fn (Builder $releases): Builder => $releases
                    ->where('is_active', true)
                    ->where(function (Builder $published): void {
                        $published
                            ->whereNull('published_at')
                            ->orWhere('published_at', '<=', now());
                    })
                    ->whereHas(
                        'downloadLinks',
                        fn (Builder $links): Builder => $links->where('is_active', true),
                    )
                    ->whereHas(
                        'languages',
                        fn (Builder $languages): Builder => $languages->where('code', $filters['language']),
                    ),
            );
        }

        foreach ($filters['tags'] as $tagSlug) {
            $query->whereHas(
                'tags',
                fn (Builder $tags): Builder => $tags->where('slug', $tagSlug),
            );
        }

        return $query;
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            self::SORT_OLDEST => $query->orderBy('published_at')->orderBy('id'),
            self::SORT_TITLE => $query->orderBy('title')->orderByDesc('published_at'),
            self::SORT_VIEWS => $query->orderByDesc('views_count')->orderByDesc('published_at'),
            default => $query->latest('published_at')->orderByDesc('id'),
        };
    }

    /**
     * @return array{
     *     categories: list<array{name: string, slug: string}>,
     *     platforms: list<array{name: string, slug: string}>,
     *     languages: list<array{name: string, code: string}>,
     *     tags: list<array{name: string, slug: string}>
     * }
     */
    private function filterOptions(): array
    {
        $publishedGames = fn (Builder $games): Builder => $games
            ->where('status', GameStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        return [
            'categories' => array_values(Category::query()
                ->whereHas('games', $publishedGames)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values()
                ->all()),
            'platforms' => array_values(Platform::query()
                ->whereHas(
                    'releases',
                    fn (Builder $releases): Builder => $releases
                        ->where('is_active', true)
                        ->where(function (Builder $published): void {
                            $published
                                ->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        })
                        ->whereHas(
                            'downloadLinks',
                            fn (Builder $links): Builder => $links->where('is_active', true),
                        )
                        ->whereHas('game', $publishedGames),
                )
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Platform $platform): array => [
                    'name' => $platform->name,
                    'slug' => $platform->slug,
                ])
                ->values()
                ->all()),
            'languages' => array_values(Language::query()
                ->whereHas(
                    'releases',
                    fn (Builder $releases): Builder => $releases
                        ->where('is_active', true)
                        ->where(function (Builder $published): void {
                            $published
                                ->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        })
                        ->whereHas(
                            'downloadLinks',
                            fn (Builder $links): Builder => $links->where('is_active', true),
                        )
                        ->whereHas('game', $publishedGames),
                )
                ->orderBy('name')
                ->get(['name', 'code'])
                ->map(fn (Language $language): array => [
                    'name' => $language->name,
                    'code' => $language->code,
                ])
                ->values()
                ->all()),
            'tags' => array_values(Tag::query()
                ->whereHas('games', $publishedGames)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])
                ->values()
                ->all()),
        ];
    }
}
