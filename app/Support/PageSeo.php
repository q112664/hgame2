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
    public const META_DESCRIPTION_MIN = 120;

    public const META_DESCRIPTION_MAX = 155;

    /** Catalog /resources — transactional listing intent. */
    private const RESOURCE_CATALOG_DESCRIPTION = 'Free hentai games and eroge downloads. Browse by genre, platform, language, or tag, then open details and grab the latest packages.';

    /** Homepage — discovery/brand, kept distinct from the catalog. */
    private const HOME_DESCRIPTION = 'Discover new hentai games and eroge. Explore popular titles and fresh listings, then open a game when you want the full story.';

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
            'description' => self::finalizeMetaDescription($description),
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
        $description = self::homeDescription();
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
     * - Unfiltered page 1: indexable, canonical /resources.
     * - Unfiltered page ≥ 2: noindex,follow (avoid near-duplicate clusters), unique title/description, self-canonical.
     * - Any filter/sort noise: fold canonical to /resources and noindex,follow.
     * - Never emit ?page=1 in the canonical.
     *
     * @return PageSeoArray
     */
    public static function resourcesIndex(int $page = 1, bool $hasFilters = false): array
    {
        $page = max(1, $page);
        $title = 'Hentai Games & Eroge Downloads';
        $canonical = route('resources.index');
        $description = self::RESOURCE_CATALOG_DESCRIPTION;
        $isPaginated = ! $hasFilters && $page > 1;

        if ($isPaginated) {
            $canonical = route('resources.index', ['page' => $page]);
            $title .= ' - Page '.$page;
            $description = self::paginatedCatalogDescription($page);
        }

        return self::make(
            title: $title,
            titleSuffix: Setting::siteLogoText(),
            description: $description,
            canonical: $canonical,
            robots: ($hasFilters || $isPaginated) ? 'noindex,follow' : null,
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
        $indexable = $isPure && $isIndexable && $page === 1;

        if ($page > 1) {
            $canonicalParams['page'] = $page;
            $title .= ' - Page '.$page;
            $description = self::paginatedTaxonomyDescription($type, $name, $page);
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
            description: 'Browse hentai game tags to find eroge by theme. Open a tag for matching titles, platforms, and free download packages.',
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
        $name = trim($name);

        return match ($type) {
            'category' => "Download free {$name} hentai games and eroge. Filter by platform and language, then open details and grab the latest packages.",
            'platform' => "Download free hentai games and eroge for {$name}. Browse titles with downloads, screenshots, and release details.",
            'language' => "Download free {$name} hentai games and eroge. Browse language-matched releases, screenshots, and the latest packages.",
            'tag' => "Download free hentai games and eroge tagged {$name}. Find related titles, platforms, and the latest download packages.",
        };
    }

    public static function paginatedCatalogDescription(int $page): string
    {
        return "Page {$page} of free hentai games and eroge. Browse more visual novels by genre, platform, and language, then download the latest packages.";
    }

    /**
     * @param  'category'|'platform'|'language'|'tag'  $type
     */
    public static function paginatedTaxonomyDescription(string $type, string $name, int $page): string
    {
        $name = trim($name);

        return match ($type) {
            'category' => "Page {$page} of {$name} hentai games and eroge. Browse more titles, then open details and download free.",
            'platform' => "Page {$page} of hentai games and eroge for {$name}. Browse more titles with downloads and screenshots.",
            'language' => "Page {$page} of {$name} hentai games and eroge. Browse more language-matched releases and download free.",
            'tag' => "Page {$page} of hentai games and eroge tagged {$name}. Browse more related titles and download packages.",
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
            'comments' => 'Reviews',
            default => null,
        };

        $title = $tabLabel !== null
            ? $game->title.' · '.$tabLabel
            : $game->title;

        $description = self::gameDescription($game, $tab);
        $image = self::gameImageUrl($game);

        $jsonLd = $tab === 'details'
            ? self::gameJsonLd($game, $description, $image)
            : null;

        // Site listing time vs download-update time — never commercial release_date,
        // never Eloquent updated_at (views/metadata must not fake freshness).
        $publishedTime = $game->sitePublishedAt()?->toIso8601String();
        $modifiedTime = $game->contentModifiedAt()?->toIso8601String();

        $isPrimary = $tab === 'details';

        return self::make(
            title: $title,
            description: $description,
            // Sub-tabs share one indexable URL so they do not form a 4-page cluster.
            canonical: route('resources.details', $game),
            ogImageUrl: $image,
            ogType: 'website',
            robots: $isPrimary ? null : 'noindex,follow',
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
            description: 'Read guides for downloading hentai games and eroge. Learn how packages, platforms, and site downloads work.',
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
            description: 'Search free hentai games and eroge by title, developer, genre, or tag, then open details and download packages.',
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

    /**
     * Strip HTML and collapse whitespace. Does not truncate.
     */
    public static function plainText(?string $value): ?string
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

        return $text === '' ? null : $text;
    }

    /**
     * Trim a meta description to $max without cutting mid-sentence when possible.
     * Falls back to the last word boundary, never mid-word.
     */
    public static function limitAtSentence(string $text, int $max = self::META_DESCRIPTION_MAX, int $min = 80): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        $window = mb_substr($text, 0, $max);
        $sentenceEnd = self::lastSentenceEndPosition($window, $min);

        if ($sentenceEnd !== null) {
            return rtrim(mb_substr($window, 0, $sentenceEnd + 1));
        }

        $space = mb_strrpos($window, ' ');

        if ($space !== false && $space >= $min) {
            return rtrim(mb_substr($window, 0, $space)).'…';
        }

        return rtrim(mb_substr($text, 0, max(1, $max - 1))).'…';
    }

    public static function finalizeMetaDescription(?string $value, int $max = self::META_DESCRIPTION_MAX): ?string
    {
        $text = self::plainText($value);

        if ($text === null) {
            return null;
        }

        return self::limitAtSentence($text, $max);
    }

    /**
     * @deprecated Use finalizeMetaDescription() for SEO payloads.
     */
    public static function plainDescription(?string $value, int $limit = self::META_DESCRIPTION_MAX): ?string
    {
        return self::finalizeMetaDescription($value, $limit);
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

    /**
     * Unique 120–155 character description with title keywords and a download CTA.
     */
    public static function gameDescription(Game $game, string $tab = 'details'): string
    {
        $title = trim((string) $game->title);
        $title = $title !== '' ? $title : 'this hentai game';
        $category = filled($game->category?->name) ? trim((string) $game->category->name) : null;
        $developer = filled($game->developer) ? trim((string) $game->developer) : null;
        $genre = $category ?? 'hentai';

        $hook = self::firstSentence(
            self::stripLeadingSynopsisLabels(self::plainText($game->description)) ?? '',
        );

        [$lead, $cta] = match ($tab) {
            'downloads' => [
                "Download {$title} — {$genre} eroge packages, file details, and the latest mirrors.",
                ' Get the latest package now.',
            ],
            'screenshots' => [
                "See screenshots of {$title}, a {$genre} hentai game. Preview scenes before you download.",
                ' Preview scenes, then download free.',
            ],
            'comments' => [
                "Read reviews of {$title}, a {$genre} hentai game. See ratings and notes from players before you download.",
                ' Share your review, then download.',
            ],
            default => [
                'Download '.$title
                    .', a '.$genre.' hentai game'
                    .($developer !== null ? ' by '.$developer : '')
                    .'.',
                ' Free download with details and screenshots.',
            ],
        };

        $text = $lead;

        if ($tab === 'details' && $hook !== '' && ! str_contains(mb_strtolower($lead), mb_strtolower($hook))) {
            $withHook = trim($text.' '.$hook);

            if (mb_strlen($withHook) <= self::META_DESCRIPTION_MAX) {
                $text = $withHook;
            }
        }

        if (mb_strlen($text) < self::META_DESCRIPTION_MIN) {
            $withCta = trim($text.$cta);

            if (mb_strlen($withCta) <= self::META_DESCRIPTION_MAX || mb_strlen($text) < 80) {
                $text = $withCta;
            }
        }

        return self::limitAtSentence($text);
    }

    public static function homeDescription(): string
    {
        $custom = Setting::get('seo_description');

        if (is_string($custom)) {
            $custom = trim($custom);

            if ($custom !== '' && mb_strlen($custom) >= 80) {
                return self::limitAtSentence($custom);
            }
        }

        return self::HOME_DESCRIPTION;
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

    /**
     * @return int<0, max>|null Byte-less Unicode offset of the last sentence terminator.
     */
    private static function lastSentenceEndPosition(string $text, int $min): ?int
    {
        $best = null;

        foreach (['.', '!', '?', '。', '！', '？'] as $mark) {
            $pos = mb_strrpos($text, $mark);

            if ($pos !== false && $pos >= $min) {
                $best = $best === null ? $pos : max($best, $pos);
            }
        }

        return $best;
    }

    private static function firstSentence(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (preg_match('/^(.+?[\.!?。！？])(?:\s|$)/u', $text, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return $text;
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
        if ($game->sitePublishedAt() !== null) {
            $data['datePublished'] = $game->sitePublishedAt()->toIso8601String();
        }

        // dateModified = last download/package change (or publish if never updated).
        if ($game->contentModifiedAt() !== null) {
            $data['dateModified'] = $game->contentModifiedAt()->toIso8601String();
        }

        if ($game->category?->name) {
            $data['genre'] = $game->category->name;
        }

        return $data;
    }
}
