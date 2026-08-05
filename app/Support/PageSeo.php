<?php

namespace App\Support;

use App\Models\Doc;
use App\Models\Game;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Page-level SEO payload for Inertia <Head> overrides.
 *
 * @phpstan-type PageSeoArray array{
 *     title: string|null,
 *     titleSuffix: string|null,
 *     description: string|null,
 *     canonical: string|null,
 *     robots: string,
 *     ogType: string,
 *     ogImageUrl: string|null,
 *     publishedTime: string|null,
 *     modifiedTime: string|null,
 *     jsonLd: array<string, mixed>|list<array<string, mixed>>|null
 * }
 */
final class PageSeo
{
    private const RESOURCE_CATALOG_DESCRIPTION = 'Browse hentai games and eroge by genre, platform, language and tags. Search by title or developer, then view release details and download information.';

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $jsonLd
     * @return PageSeoArray
     */
    public static function make(
        ?string $title = null,
        ?string $titleSuffix = null,
        ?string $description = null,
        ?string $canonical = null,
        ?string $ogImageUrl = null,
        ?string $robots = null,
        string $ogType = 'website',
        ?string $publishedTime = null,
        ?string $modifiedTime = null,
        ?array $jsonLd = null,
    ): array {
        return [
            'title' => filled($title) ? trim((string) $title) : null,
            'titleSuffix' => filled($titleSuffix) ? trim((string) $titleSuffix) : null,
            'description' => self::plainDescription($description),
            'canonical' => self::absoluteUrl($canonical),
            'robots' => self::resolveRobots($robots),
            'ogType' => $ogType !== '' ? $ogType : 'website',
            'ogImageUrl' => self::absoluteUrl($ogImageUrl) ?? self::absoluteUrl(Setting::seoOgImageUrl()),
            'publishedTime' => filled($publishedTime) ? trim((string) $publishedTime) : null,
            'modifiedTime' => filled($modifiedTime) ? trim((string) $modifiedTime) : null,
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * @return PageSeoArray
     */
    public static function home(): array
    {
        return self::make(
            title: null,
            description: Setting::seoDescription(),
            canonical: route('home'),
            ogImageUrl: Setting::seoOgImageUrl(),
            robots: Setting::seoRobots(),
        );
    }

    /**
     * Catalog SEO: clean page 1 URL is the default.
     *
     * - Unfiltered page ≥ 2: self-referencing canonical (?page=N) and title suffix.
     * - Any filter/sort noise (or filtered deep pages): fold canonical to /resources.
     * - Never emit ?page=1 in the canonical.
     *
     * @return PageSeoArray
     */
    public static function resourcesIndex(int $page = 1, bool $hasFilters = false): array
    {
        $page = max(1, $page);
        $title = 'Hentai Games & Eroge Downloads';
        $canonical = route('resources.index');

        if (! $hasFilters && $page > 1) {
            $canonical = route('resources.index', ['page' => $page]);
            $title .= ' - Page '.$page;
        }

        return self::make(
            title: $title,
            titleSuffix: Setting::siteLogoText(),
            description: self::RESOURCE_CATALOG_DESCRIPTION,
            canonical: $canonical,
            robots: $hasFilters ? 'noindex,follow' : null,
        );
    }

    /**
     * @return PageSeoArray
     */
    public static function forGame(Game $game, string $tab = 'details'): array
    {
        if (! $game->relationLoaded('category')) {
            $game->load('category:id,name');
        }

        $canonicalRoute = match ($tab) {
            'downloads' => 'resources.downloads',
            'screenshots' => 'resources.screenshots',
            'comments' => 'resources.comments',
            default => 'resources.details',
        };

        $tabLabel = match ($tab) {
            'downloads' => 'Downloads',
            'screenshots' => 'Screenshots',
            'comments' => 'Comments',
            default => null,
        };

        $title = $tabLabel !== null
            ? $game->title.' · '.$tabLabel
            : $game->title;

        $description = self::gameDescription($game);
        $image = self::gameImageUrl($game);

        $jsonLd = $tab === 'details'
            ? self::gameJsonLd($game, $description, $image)
            : null;

        // Page publish / modify times for crawlers (site-side, not commercial release_date).
        $publishedTime = $game->published_at?->toIso8601String();
        $modified = $game->downloads_updated_at
            ?? $game->updated_at
            ?? $game->published_at;
        $modifiedTime = $modified?->toIso8601String();

        return self::make(
            title: $title,
            description: $description,
            canonical: route($canonicalRoute, $game),
            ogImageUrl: $image,
            ogType: 'website',
            publishedTime: $publishedTime,
            modifiedTime: $modifiedTime,
            jsonLd: $jsonLd,
        );
    }

    /**
     * @return PageSeoArray
     */
    public static function docsIndex(): array
    {
        return self::make(
            title: 'Articles',
            description: 'Guides and site documentation.',
            canonical: route('docs.index'),
        );
    }

    /**
     * @return PageSeoArray
     */
    public static function forDoc(Doc $doc): array
    {
        $description = filled($doc->excerpt)
            ? (string) $doc->excerpt
            : self::plainDescription(strip_tags((string) ($doc->body ?? '')));

        $image = filled($doc->cover_path)
            ? Media::url((string) $doc->cover_path)
            : Setting::seoOgImageUrl();

        $canonical = route('docs.show', $doc);

        return self::make(
            title: $doc->title,
            description: $description,
            canonical: $canonical,
            ogImageUrl: $image,
            ogType: 'article',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $doc->title,
                'description' => self::plainDescription($description) ?? '',
                'image' => array_values(array_filter([self::absoluteUrl($image)])),
                'datePublished' => $doc->published_at?->toIso8601String(),
                'mainEntityOfPage' => self::absoluteUrl($canonical),
            ],
        );
    }

    /**
     * @return PageSeoArray
     */
    public static function search(): array
    {
        return self::make(
            title: 'Search',
            description: 'Search published resources.',
            canonical: route('search'),
            robots: 'noindex,follow',
        );
    }

    /**
     * @return PageSeoArray
     */
    public static function noindex(string $title, ?string $canonical = null): array
    {
        return self::make(
            title: $title,
            description: null,
            canonical: $canonical,
            robots: 'noindex,nofollow',
            ogImageUrl: null,
        );
    }

    public static function absoluteUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $url = trim((string) $url);

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $base = rtrim(Setting::siteUrl(), '/');

        if (Str::startsWith($url, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$url;
        }

        return $base.'/'.ltrim($url, '/');
    }

    public static function plainDescription(?string $value, int $limit = 160): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit, '…');
    }

    private static function resolveRobots(?string $pageRobots): string
    {
        $siteRobots = Setting::seoRobots();

        if ($pageRobots === null || $pageRobots === '') {
            return $siteRobots;
        }

        if ($siteRobots === 'noindex,nofollow' || $pageRobots === 'noindex,nofollow') {
            return 'noindex,nofollow';
        }

        if ($siteRobots === 'noindex,follow' || $pageRobots === 'noindex,follow') {
            return 'noindex,follow';
        }

        return 'index,follow';
    }

    private static function gameDescription(Game $game): ?string
    {
        $fromBody = self::plainDescription($game->description);

        if ($fromBody !== null) {
            return $fromBody;
        }

        $parts = array_values(array_filter([
            $game->subtitle,
            $game->developer ? 'by '.$game->developer : null,
            $game->category?->name,
        ], filled(...)));

        return self::plainDescription(implode(' · ', $parts));
    }

    private static function gameImageUrl(Game $game): ?string
    {
        if (filled($game->cover_path)) {
            // Prefer full cover for social cards when available.
            $cover = Media::url((string) $game->cover_path);

            if ($cover !== '') {
                return $cover;
            }
        }

        if (filled($game->cover_url)) {
            return (string) $game->cover_url;
        }

        return Setting::seoOgImageUrl();
    }

    /**
     * @return array<string, mixed>
     */
    private static function gameJsonLd(Game $game, ?string $description, ?string $image): array
    {
        $url = self::absoluteUrl(route('resources.details', $game));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $game->title,
            'url' => $url,
            'applicationCategory' => 'GameApplication',
        ];

        if ($game->relationLoaded('releases')) {
            $platforms = $game->releases
                ->flatMap(fn ($release) => $release->relationLoaded('platforms')
                    ? $release->platforms->pluck('name')
                    : collect())
                ->unique()
                ->filter()
                ->values()
                ->all();

            if ($platforms !== []) {
                $data['operatingSystem'] = implode(', ', $platforms);
            }
        }

        if ($description !== null) {
            $data['description'] = $description;
        }

        $absoluteImage = self::absoluteUrl($image);

        if ($absoluteImage !== null) {
            $data['image'] = $absoluteImage;
        }

        if (filled($game->developer)) {
            $data['author'] = [
                '@type' => 'Organization',
                'name' => $game->developer,
            ];
        }

        // datePublished = when this resource page went live on the site.
        if ($game->published_at !== null) {
            $data['datePublished'] = $game->published_at->toIso8601String();
        }

        $modified = $game->downloads_updated_at
            ?? $game->updated_at
            ?? $game->published_at;

        if ($modified !== null) {
            $data['dateModified'] = $modified->toIso8601String();
        }

        if ($game->category?->name) {
            $data['genre'] = $game->category->name;
        }

        return $data;
    }
}
