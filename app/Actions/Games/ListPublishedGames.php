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
    public const PER_PAGE = 12;

    /** Default catalog order: newest site listing (published_at). */
    public const SORT_LATEST = 'latest';

    public const SORT_OLDEST = 'oldest';

    /**
     * “Last updated”: the most recent download package change, falling back to
     * the listed date for resources that were never re-packaged. Every resource
     * has a value, so this ordering never hides anything.
     */
    public const SORT_UPDATED = 'updated';

    public const SORT_TITLE = 'title';

    public const SORT_VIEWS = 'views';

    /** Ordering direction, shared by every sort field. */
    public const DIR_ASC = 'asc';

    public const DIR_DESC = 'desc';

    /** @var list<string> */
    public const DIRECTIONS = [
        self::DIR_ASC,
        self::DIR_DESC,
    ];

    /** @var list<string> */
    public const SORTS = [
        self::SORT_LATEST,
        self::SORT_OLDEST,
        self::SORT_UPDATED,
        self::SORT_TITLE,
        self::SORT_VIEWS,
    ];

    /**
     * Canonical ordering for a requested sort field + direction, so equivalent
     * URLs (e.g. `sort=latest&dir=asc` and `sort=oldest`) collapse into one
     * result set and one URL.
     *
     * @return array{sort: string, dir: string}
     */
    public static function normalizeSort(?string $sort, ?string $dir): array
    {
        $sort ??= self::SORT_LATEST;
        $knownDir = in_array($dir, self::DIRECTIONS, true) ? $dir : null;

        // Unknown values are passed through so validation can reject them.
        if (! in_array($sort, self::SORTS, true) || ($dir !== null && $knownDir === null)) {
            return [
                'sort' => $sort,
                'dir' => $dir ?? self::defaultDirection($sort),
            ];
        }

        if ($sort === self::SORT_LATEST || $sort === self::SORT_OLDEST) {
            $direction = $knownDir ?? self::defaultDirection($sort);

            return [
                'sort' => $direction === self::DIR_ASC ? self::SORT_OLDEST : self::SORT_LATEST,
                'dir' => $direction,
            ];
        }

        return [
            'sort' => $sort,
            'dir' => $knownDir ?? self::defaultDirection($sort),
        ];
    }

    /** Direction a sort field uses when the URL omits `dir`. */
    public static function defaultDirection(string $sort): string
    {
        return $sort === self::SORT_OLDEST || $sort === self::SORT_TITLE
            ? self::DIR_ASC
            : self::DIR_DESC;
    }

    /**
     * @param  array{q: string, category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string, dir: string}  $filters
     * @return array{
     *     resources: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array{q: string, category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string, dir: string},
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
        $paginator = $this->applySort($this->query($filters), $filters)
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
     * @param  array{q: string, category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string, dir: string}  $filters
     * @return Builder<Game>
     */
    private function query(array $filters): Builder
    {
        $query = Game::query()->published();

        if (filled($filters['q'])) {
            $query->matchingSearch($filters['q']);
        }

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
     * @param  array{q: string, category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string, dir: string}  $filters
     * @return Builder<Game>
     */
    private function applySort(Builder $query, array $filters): Builder
    {
        $direction = $filters['dir'] === self::DIR_ASC ? self::DIR_ASC : self::DIR_DESC;

        return match ($filters['sort']) {
            self::SORT_OLDEST => $query->orderBy('published_at', self::DIR_ASC)->orderBy('id'),
            self::SORT_TITLE => $query->orderBy('title', $direction)->orderByDesc('published_at'),
            self::SORT_VIEWS => $query->orderBy('views_count', $direction)->orderByDesc('published_at'),
            // Resources without a package change fall back to their listed date,
            // so the ordering covers the whole catalog on one timeline.
            self::SORT_UPDATED => $query
                ->orderByRaw('coalesce(downloads_updated_at, published_at) '.$direction)
                ->orderByDesc('id'),
            // Default “Latest” = newest listed on this site (not download updates).
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
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
                ->withCount(['games' => $publishedGames])
                ->orderByDesc('games_count')
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
                ->withCount(['games' => $publishedGames])
                ->orderByDesc('games_count')
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
