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
        $siteUrl = Setting::siteUrl();
        $name = Setting::siteTitle();
        $description = Setting::seoDescription();
        $searchTarget = rtrim($siteUrl, '/').'/search?q={search_term_string}';

        return self::make(
            title: null,
            description: $description,
            canonical: route('home'),
            ogImageUrl: Setting::seoOgImageUrl(),
            robots: Setting::seoRobots(),
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => $siteUrl,
                'description' => $description,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => $searchTarget,
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
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
     * Single-dimension taxonomy landing pages (genre / platform / language / tag).
     *
     * Pure pages (no search, no extra dims, default sort) are indexable with a
     * self-referencing canonical. Extra query noise folds the canonical back to
     * the clean taxonomy URL and uses noindex.
     *
     * @param  'category'|'platform'|'language'|'tag'  $type
     * @return PageSeoArray
     */
    public static function resourcesTaxonomy(
        string $type,
        string $name,
        string $value,
        int $page = 1,
        bool $isPure = true,
        bool $isIndexable = true,
    ): array {
        $page = max(1, $page);
        $name = trim($name);
        $route = self::taxonomyRouteName($type);
        $params = self::taxonomyRouteParams($type, $value);
        $title = self::taxonomyTitle($type, $name);
        $description = self::taxonomyDescription($type, $name);

        $canonicalParams = $params;
        $indexable = $isPure && $isIndexable;

        if ($indexable && $page > 1) {
            $canonicalParams['page'] = $page;
            $title .= ' - Page '.$page;
        }

        return self::make(
            title: $title,
            titleSuffix: Setting::siteLogoText(),
            description: $description,
            canonical: route($route, $canonicalParams),
            robots: $indexable ? null : 'noindex,follow',
        );
    }

    /**
     * Dedicated tag directory page (/resources/tags).
     *
     * @return PageSeoArray
     */
    public static function resourcesTagsIndex(): array
    {
        return self::make(
            title: 'Game Tags',
            titleSuffix: Setting::siteLogoText(),
            description: 'Browse hentai games and eroge by tag. Open a tag to see matching titles, platforms, and download packages.',
            canonical: route('resources.tags'),
        );
    }

    /**
     * @param  'category'|'platform'|'language'|'tag'  $type
     */
    public static function taxonomyRouteName(string $type): string
    {
        return match ($type) {
            'category' => 'resources.genre',
            'platform' => 'resources.platform',
            'language' => 'resources.language',
            'tag' => 'resources.tag',
        };
    }

    /**
     * @param  'category'|'platform'|'language'|'tag'  $type
     * @return array<string, string>
     */
    public static function taxonomyRouteParams(string $type, string $value): array
    {
        return match ($type) {
            'category' => ['category' => $value],
            'platform' => ['platform' => $value],
            'language' => ['language' => $value],
            'tag' => ['tag' => $value],
        };
    }

    /**
     * @param  'category'|'platform'|'language'|'tag'  $type
     */
    public static function taxonomyTitle(string $type, string $name): string
    {
        return match ($type) {
            'category' => "{$name} Hentai Games & Eroge",
            'platform' => "{$name} Hentai Games & Eroge Downloads",
            'language' => "{$name} Hentai Games & Eroge",
            'tag' => "{$name} Hentai Games & Eroge",
        };
    }

    /**
     * @param  'category'|'platform'|'language'|'tag'  $type
     */
    public static function taxonomyDescription(string $type, string $name): string
    {
        return match ($type) {
            'category' => "Browse {$name} hentai games and eroge. Filter by platform and language, then open release details and download links.",
            'platform' => "Browse hentai games and eroge for {$name}. Discover titles with downloads, screenshots, and release information.",
            'language' => "Browse {$name} hentai games and eroge with language-matched releases, downloads, and screenshots.",
            'tag' => "Browse hentai games and eroge tagged {$name}. Find related titles, platforms, and download packages.",
        };
    }

    /**
     * @return PageSeoArray
     */
    public static function forGame(Game $game, string $tab = 'details'): array
    {
        if (! $game->relationLoaded('category')) {
            $game->load('category:id,name,slug');
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
        $absoluteImage = self::absoluteUrl($image);

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
                'image' => $absoluteImage !== null ? [$absoluteImage] : [],
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

        $html = (string) $value;

        // Preserve word boundaries when stripping block-level HTML.
        $html = preg_replace('/<\s*br\s*\/?>/iu', ' ', $html) ?? $html;
        $html = preg_replace(
            '/<\/(?:p|div|h[1-6]|li|tr|td|th|blockquote|section|article|header|footer|figcaption|pre|dt|dd)\s*>/iu',
            ' ',
            $html,
        ) ?? $html;
        $html = preg_replace(
            '/<(?:p|div|h[1-6]|li|tr|td|th|blockquote|section|article|header|footer|figcaption|pre|dt|dd)\b[^>]*>/iu',
            ' ',
            $html,
        ) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Decorative bullets / list markers often glued to titles.
        $text = preg_replace('/^[\s◆★■●▪◦•·‧]+/u', '', $text) ?? $text;
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
        $fromBody = self::stripLeadingSynopsisLabels($fromBody);

        if ($fromBody !== null && mb_strlen($fromBody) >= 40) {
            return Str::limit($fromBody, 160, '…');
        }

        $parts = array_values(array_filter([
            $game->subtitle,
            $game->developer ? 'by '.$game->developer : null,
            $game->category?->name,
        ], filled(...)));

        $fallback = self::plainDescription(implode(' · ', $parts));

        // Prefer a short cleaned body over empty; else structured fallback.
        if ($fromBody !== null && $fromBody !== '') {
            return Str::limit($fromBody, 160, '…');
        }

        return $fallback;
    }

    /**
     * Drop common pseudo-headings glued to AI/import synopsis blocks.
     */
    private static function stripLeadingSynopsisLabels(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $patterns = [
            // Synopsis (AI-translated English) …
            '/^(?:synopsis|story|game\s*story|game\s*description|description|overview|plot|summary)(?:\s*\([^)]*\))?\s*[:：\-–—]?\s*/iu',
        ];

        $cleaned = $text;

        foreach ($patterns as $pattern) {
            $next = preg_replace($pattern, '', $cleaned, 1);

            if (is_string($next) && $next !== $cleaned) {
                $cleaned = trim($next);
            }
        }

        // Leading decorative markers again after label strip.
        $cleaned = preg_replace('/^[\s◆★■●▪◦•·‧]+/u', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
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
