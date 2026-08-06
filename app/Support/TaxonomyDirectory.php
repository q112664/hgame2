<?php

namespace App\Support;

use App\GameStatus;
use App\Models\Category;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Public taxonomy links for internal SEO navigation.
 *
 * @phpstan-type TaxonomyLink array{name: string, value: string}
 * @phpstan-type TaxonomyNav array{
 *     categories: list<TaxonomyLink>,
 *     platforms: list<TaxonomyLink>,
 *     languages: list<TaxonomyLink>,
 *     tags: list<TaxonomyLink>
 * }
 */
final class TaxonomyDirectory
{
    public const string CacheKey = 'taxonomy.directory.nav.v1';

    /** Popular tags shown in the full browse directory. */
    public const int TagLimit = 48;

    /**
     * Minimum published games for a tag landing page to be indexable / in sitemap.
     * Thin tag pages (1–2 games) stay reachable but noindex.
     */
    public const int MinPublishedGamesForIndex = 3;

    public static function isIndexablePublishedCount(int $count): bool
    {
        return $count >= self::MinPublishedGamesForIndex;
    }

    /**
     * Published game count for a single tag (for robots / sitemap decisions).
     */
    public static function publishedGameCountForTag(Tag $tag): int
    {
        return (int) $tag->games()
            ->where('status', GameStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->count();
    }

    /**
     * @return TaxonomyNav
     */
    public static function navigation(): array
    {
        /** @var TaxonomyNav $nav */
        $nav = Cache::remember(
            self::CacheKey,
            now()->addHour(),
            fn (): array => self::build(),
        );

        return $nav;
    }

    public static function forget(): void
    {
        Cache::forget(self::CacheKey);
        Cache::forget(self::TagsIndexCacheKey);
    }

    public const string TagsIndexCacheKey = 'taxonomy.directory.tags-index.v1';

    /**
     * Full tag directory for the dedicated Tags index page (all tags with counts).
     *
     * @return list<array{name: string, value: string, count: int}>
     */
    public static function tagsIndex(): array
    {
        /** @var list<array{name: string, value: string, count: int}> $tags */
        $tags = Cache::remember(
            self::TagsIndexCacheKey,
            now()->addHour(),
            fn (): array => self::buildTagsIndex(),
        );

        return $tags;
    }

    /**
     * @return list<array{name: string, value: string, count: int}>
     */
    private static function buildTagsIndex(): array
    {
        $publishedGames = self::publishedGamesConstraint();

        return Tag::query()
            ->whereHas('games', $publishedGames)
            ->withCount(['games' => $publishedGames])
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (Tag $tag): array => [
                'name' => $tag->name,
                'value' => $tag->slug,
                'count' => (int) $tag->games_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Closure(Builder): Builder
     */
    private static function publishedGamesConstraint(): \Closure
    {
        return static function (Builder $games): Builder {
            return $games
                ->where('status', GameStatus::Published)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        };
    }

    /**
     * @return TaxonomyNav
     */
    private static function build(): array
    {
        $publishedGames = self::publishedGamesConstraint();

        $activeRelease = static function (Builder $releases) use ($publishedGames): Builder {
            return $releases
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
                ->whereHas('game', $publishedGames);
        };

        return [
            'categories' => Category::query()
                ->whereHas('games', $publishedGames)
                ->withCount(['games' => $publishedGames])
                ->orderByDesc('games_count')
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'value' => $category->slug,
                ])
                ->values()
                ->all(),
            'platforms' => Platform::query()
                ->whereHas('releases', $activeRelease)
                ->withCount(['releases' => $activeRelease])
                ->orderByDesc('releases_count')
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Platform $platform): array => [
                    'name' => $platform->name,
                    'value' => $platform->slug,
                ])
                ->values()
                ->all(),
            'languages' => Language::query()
                ->whereHas('releases', $activeRelease)
                ->withCount(['releases' => $activeRelease])
                ->orderByDesc('releases_count')
                ->orderBy('name')
                ->get(['name', 'code'])
                ->map(fn (Language $language): array => [
                    'name' => $language->name,
                    'value' => $language->code,
                ])
                ->values()
                ->all(),
            'tags' => Tag::query()
                ->whereHas('games', $publishedGames)
                ->withCount(['games' => $publishedGames])
                ->orderByDesc('games_count')
                ->orderBy('name')
                ->limit(self::TagLimit)
                ->get(['name', 'slug'])
                ->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'value' => $tag->slug,
                ])
                ->values()
                ->all(),
        ];
    }
}
