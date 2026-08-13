<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Throwable;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    public const int DEFAULT_COVER_THUMBNAIL_MAX_WIDTH = 560;

    public const int DEFAULT_COVER_THUMBNAIL_QUALITY = 80;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! static::tableReady()) {
            return $default;
        }

        /** @var string|null $value */
        $value = Cache::rememberForever(
            static::cacheKey($key),
            fn (): ?string => static::query()->where('key', $key)->value('value'),
        );

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::forget(static::cacheKey($key));

        if ($key === 'site_url') {
            static::applySiteUrlToConfig($value);
        }
    }

    public static function siteUrl(): string
    {
        return rtrim((string) (static::get('site_url') ?: config('app.url')), '/');
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setBoolean(string $key, bool $enabled): void
    {
        static::set($key, $enabled ? '1' : '0');
    }

    public static function coverThumbnailMaxWidth(): int
    {
        $value = (int) (static::get('cover_thumbnail_max_width') ?? self::DEFAULT_COVER_THUMBNAIL_MAX_WIDTH);

        if ($value < 120 || $value > 2000) {
            return self::DEFAULT_COVER_THUMBNAIL_MAX_WIDTH;
        }

        return $value;
    }

    public static function coverThumbnailQuality(): int
    {
        $value = (int) (static::get('cover_thumbnail_quality') ?? self::DEFAULT_COVER_THUMBNAIL_QUALITY);

        if ($value < 1 || $value > 100) {
            return self::DEFAULT_COVER_THUMBNAIL_QUALITY;
        }

        return $value;
    }

    public static function defaultSiteLogoText(): string
    {
        return (string) config('app.name', 'hgame');
    }

    public static function defaultSiteTitle(): string
    {
        return static::defaultSiteLogoText();
    }

    /**
     * Browser tab title suffix (e.g. "Resources - {siteTitle}").
     */
    public static function siteTitle(): string
    {
        $title = static::get('site_title');

        if (filled($title)) {
            return (string) $title;
        }

        return static::siteLogoText();
    }

    public static function defaultSeoDescription(): string
    {
        return 'Download free hentai games and eroge. Browse visual novels by genre, platform, and language, then grab the latest packages.';
    }

    public static function seoDescription(): string
    {
        $description = static::get('seo_description');

        return filled($description)
            ? (string) $description
            : static::defaultSeoDescription();
    }

    public static function seoKeywords(): string
    {
        return (string) (static::get('seo_keywords') ?? '');
    }

    public static function seoRobots(): string
    {
        $robots = static::get('seo_robots', 'index,follow');

        return in_array($robots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)
            ? $robots
            : 'index,follow';
    }

    public static function seoOgImagePath(): ?string
    {
        $path = static::get('seo_og_image_path');

        return filled($path) ? $path : null;
    }

    public static function faviconPath(): ?string
    {
        $path = static::get('site_favicon_path');

        return filled($path) ? $path : null;
    }

    public static function faviconUrl(): ?string
    {
        $path = static::faviconPath();

        if ($path === null) {
            return null;
        }

        $url = Media::url($path);

        return $url !== '' ? $url : null;
    }

    public static function seoOgImageUrl(): ?string
    {
        $path = static::seoOgImagePath();

        if ($path === null) {
            return static::siteLogoImageUrl();
        }

        $url = Media::url($path);

        return $url !== '' ? $url : static::siteLogoImageUrl();
    }

    public static function seoGoogleSiteVerification(): string
    {
        return (string) (static::get('seo_google_site_verification') ?? '');
    }

    /**
     * Site-wide SEO defaults for SiteSeo (Inertia Head) and Blade CSR fallbacks.
     * Blade tags use matching data-inertia keys so hydrate does not duplicate them.
     *
     * @return array{
     *     description: string,
     *     keywords: string,
     *     robots: string,
     *     ogImageUrl: string|null,
     *     faviconUrl: string|null,
     *     googleSiteVerification: string
     * }
     */
    public static function seo(): array
    {
        return [
            'description' => static::seoDescription(),
            'keywords' => static::seoKeywords(),
            'robots' => static::seoRobots(),
            'ogImageUrl' => static::seoOgImageUrl(),
            'faviconUrl' => static::faviconUrl(),
            'googleSiteVerification' => static::seoGoogleSiteVerification(),
        ];
    }

    public static function siteLogoText(): string
    {
        $text = static::get('site_logo_text');

        return filled($text) ? (string) $text : static::defaultSiteLogoText();
    }

    public static function siteLogoPath(): ?string
    {
        $path = static::get('site_logo_path');

        return filled($path) ? $path : null;
    }

    public static function siteLogoImageUrl(): ?string
    {
        $path = static::siteLogoPath();

        if ($path === null) {
            return null;
        }

        $url = Media::url($path);

        return $url !== '' ? $url : null;
    }

    /**
     * @return 'text'|'image'|'both'
     */
    public static function siteLogoMode(): string
    {
        $mode = static::get('site_logo_mode', 'text');

        return in_array($mode, ['text', 'image', 'both'], true) ? $mode : 'text';
    }

    /**
     * @return array{mode: 'text'|'image'|'both', text: string, imageUrl: string|null}
     */
    public static function siteLogo(): array
    {
        $mode = static::siteLogoMode();
        $text = static::siteLogoText();
        $imageUrl = static::siteLogoImageUrl();

        if ($mode === 'image' && $imageUrl === null) {
            $mode = 'text';
        }

        if ($mode === 'both' && $imageUrl === null) {
            $mode = 'text';
        }

        return [
            'mode' => $mode,
            'text' => $text,
            'imageUrl' => $imageUrl,
        ];
    }

    public static function defaultHeroBackgroundUrl(): string
    {
        return '/images/hero-bg.jpg';
    }

    public static function heroBackgroundPath(): ?string
    {
        $path = static::get('hero_background_path');

        return filled($path) ? $path : null;
    }

    public static function heroBackgroundUrl(): string
    {
        $path = static::heroBackgroundPath();

        if ($path === null) {
            return static::defaultHeroBackgroundUrl();
        }

        $url = Media::url($path);

        return $url !== '' ? $url : static::defaultHeroBackgroundUrl();
    }

    public static function defaultHeroDescription(): string
    {
        return 'Browse, search, and download galgame packages.';
    }

    public static function defaultHeroBrowseLabel(): string
    {
        return 'Browse';
    }

    public static function defaultHeroRandomLabel(): string
    {
        return 'Random';
    }

    /**
     * Homepage hero copy and controls for the public welcome page.
     *
     * @return array{
     *     backgroundUrl: string,
     *     title: string,
     *     description: string,
     *     browseLabel: string,
     *     randomLabel: string,
     *     showBrowse: bool,
     *     showRandom: bool
     * }
     */
    public static function homeHero(): array
    {
        $title = trim((string) (static::get('hero_title') ?? ''));

        if ($title === '') {
            $title = static::siteLogoText();
        }

        $description = trim((string) (static::get('hero_description') ?? ''));
        $browseLabel = trim((string) (static::get('hero_browse_label') ?? ''));
        $randomLabel = trim((string) (static::get('hero_random_label') ?? ''));

        return [
            'backgroundUrl' => static::heroBackgroundUrl(),
            'title' => $title,
            'description' => $description !== '' ? $description : static::defaultHeroDescription(),
            'browseLabel' => $browseLabel !== '' ? $browseLabel : static::defaultHeroBrowseLabel(),
            'randomLabel' => $randomLabel !== '' ? $randomLabel : static::defaultHeroRandomLabel(),
            'showBrowse' => static::boolean('hero_show_browse', true),
            'showRandom' => static::boolean('hero_show_random', true),
        ];
    }

    public static function resourceNoticeEnabled(): bool
    {
        return static::boolean('resource_notice_enabled', false);
    }

    /**
     * Sanitized HTML for the resource-page notice above the download CTA.
     * Empty when disabled or when the editor has no meaningful content.
     */
    public static function resourceNoticeHtml(): string
    {
        if (! static::resourceNoticeEnabled()) {
            return '';
        }

        $raw = (string) (static::get('resource_notice_content') ?? '');

        if ($raw === '') {
            return '';
        }

        $sanitized = str($raw)->sanitizeHtml()->toString();

        if (trim(strip_tags($sanitized, '<img>')) === '' && ! str_contains($sanitized, '<img')) {
            return '';
        }

        return $sanitized;
    }

    /**
     * @return list<array{label: string, url: string, icon: string|null, open_in_new_tab: bool, match: 'exact'|'prefix'|'none'}>
     */
    public static function defaultNavigationMenu(): array
    {
        return [
            [
                'label' => 'Home',
                'url' => '/',
                'icon' => 'Home',
                'open_in_new_tab' => false,
                'match' => 'exact',
            ],
            [
                'label' => 'Resources',
                'url' => '/resources',
                'icon' => 'Library',
                'open_in_new_tab' => false,
                'match' => 'prefix',
            ],
            [
                'label' => 'Docs',
                'url' => '/docs',
                'icon' => 'BookOpen',
                'open_in_new_tab' => false,
                'match' => 'prefix',
            ],
            [
                'label' => 'Random',
                'url' => '/resources/random',
                'icon' => 'Dices',
                'open_in_new_tab' => false,
                'match' => 'none',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function navigationMenuIconOptions(): array
    {
        return [
            'BookOpen' => 'Book open',
            'Dices' => 'Dices',
            'ExternalLink' => 'External link',
            'Home' => 'Home',
            'Library' => 'Library',
            'Search' => 'Search',
            'Sparkles' => 'Sparkles',
            'Star' => 'Star',
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string|null, openInNewTab: bool, match: 'exact'|'prefix'|'none'}>
     */
    public static function navigationMenu(): array
    {
        $raw = static::get('navigation_menu');

        if (! filled($raw)) {
            return static::presentNavigationMenu(static::defaultNavigationMenu());
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return static::presentNavigationMenu(static::defaultNavigationMenu());
        }

        if (! is_array($decoded)) {
            return static::presentNavigationMenu(static::defaultNavigationMenu());
        }

        return static::presentNavigationMenu($decoded);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function setNavigationMenu(array $items): void
    {
        $normalized = array_values(array_filter(
            array_map(fn (mixed $item): ?array => static::normalizeNavigationMenuItem($item), $items),
        ));

        if ($normalized === []) {
            $normalized = static::defaultNavigationMenu();
        }

        static::set('navigation_menu', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string|int, mixed>  $items
     * @return list<array{label: string, url: string, icon: string|null, openInNewTab: bool, match: 'exact'|'prefix'|'none'}>
     */
    protected static function presentNavigationMenu(array $items): array
    {
        $normalized = array_values(array_filter(
            array_map(fn (mixed $item): ?array => static::normalizeNavigationMenuItem($item), $items),
        ));

        if ($normalized === []) {
            $normalized = static::defaultNavigationMenu();
        }

        return array_map(fn (array $item): array => [
            'label' => $item['label'],
            'url' => $item['url'],
            'icon' => $item['icon'],
            'openInNewTab' => $item['open_in_new_tab'],
            'match' => $item['match'],
        ], $normalized);
    }

    /**
     * @return array{label: string, url: string, icon: string|null, open_in_new_tab: bool, match: 'exact'|'prefix'|'none'}|null
     */
    protected static function normalizeNavigationMenuItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));

        if ($label === '' || $url === '' || ! static::isValidNavigationMenuUrl($url)) {
            return null;
        }

        $icon = $item['icon'] ?? null;
        $icon = is_string($icon) && $icon !== '' ? $icon : null;

        if ($icon !== null && ! array_key_exists($icon, static::navigationMenuIconOptions())) {
            $icon = null;
        }

        // Sensible defaults for built-in routes when no icon is set.
        if ($icon === null) {
            $icon = static::defaultNavigationIconForUrl($url);
        }

        $match = $item['match'] ?? 'prefix';

        if (! in_array($match, ['exact', 'prefix', 'none'], true)) {
            $match = 'prefix';
        }

        $openInNewTab = filter_var(
            $item['open_in_new_tab'] ?? $item['openInNewTab'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        return [
            'label' => mb_substr($label, 0, 80),
            'url' => mb_substr($url, 0, 2048),
            'icon' => $icon,
            'open_in_new_tab' => $openInNewTab,
            'match' => $match,
        ];
    }

    public static function defaultNavigationIconForUrl(string $url): ?string
    {
        $path = $url;

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            try {
                $path = parse_url($url, PHP_URL_PATH) ?: '/';
            } catch (Throwable) {
                return null;
            }
        }

        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return 'Home';
        }

        if ($path === '/resources/random' || str_starts_with($path, '/resources/random/')) {
            return 'Dices';
        }

        if ($path === '/resources' || str_starts_with($path, '/resources/')) {
            return 'Library';
        }

        if ($path === '/docs' || str_starts_with($path, '/docs/')) {
            return 'BookOpen';
        }

        if ($path === '/search' || str_starts_with($path, '/search/')) {
            return 'Search';
        }

        if ($path === '/favorites' || str_starts_with($path, '/favorites/')) {
            return 'Star';
        }

        return null;
    }

    public static function isValidNavigationMenuUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /**
     * Default public footer links (empty — admins add DMCA, Contact, etc.).
     *
     * @return list<array{label: string, url: string, open_in_new_tab: bool}>
     */
    public static function defaultFooterLinks(): array
    {
        return [];
    }

    /**
     * @return list<array{label: string, url: string, openInNewTab: bool}>
     */
    public static function footerLinks(): array
    {
        $raw = static::get('footer_links');

        if (! filled($raw)) {
            return static::presentFooterLinks(static::defaultFooterLinks());
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return static::presentFooterLinks(static::defaultFooterLinks());
        }

        if (! is_array($decoded)) {
            return static::presentFooterLinks(static::defaultFooterLinks());
        }

        return static::presentFooterLinks($decoded);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function setFooterLinks(array $items): void
    {
        $normalized = array_values(array_filter(
            array_map(fn (mixed $item): ?array => static::normalizeFooterLinkItem($item), $items),
        ));

        static::set('footer_links', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string|int, mixed>  $items
     * @return list<array{label: string, url: string, openInNewTab: bool}>
     */
    protected static function presentFooterLinks(array $items): array
    {
        $normalized = array_values(array_filter(
            array_map(fn (mixed $item): ?array => static::normalizeFooterLinkItem($item), $items),
        ));

        return array_map(fn (array $item): array => [
            'label' => $item['label'],
            'url' => $item['url'],
            'openInNewTab' => $item['open_in_new_tab'],
        ], $normalized);
    }

    /**
     * @return array{label: string, url: string, open_in_new_tab: bool}|null
     */
    protected static function normalizeFooterLinkItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));

        if ($label === '' || $url === '' || ! static::isValidNavigationMenuUrl($url)) {
            return null;
        }

        $openInNewTab = filter_var(
            $item['open_in_new_tab'] ?? $item['openInNewTab'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        return [
            'label' => mb_substr($label, 0, 80),
            'url' => mb_substr($url, 0, 2048),
            'open_in_new_tab' => $openInNewTab,
        ];
    }

    public static function applySiteUrlToConfig(?string $url = null): void
    {
        $siteUrl = rtrim($url ?? static::siteUrl(), '/');

        if ($siteUrl === '') {
            return;
        }

        config([
            'app.url' => $siteUrl,
            'filesystems.disks.public.url' => $siteUrl.'/storage',
        ]);

        // Keep Wayfinder/Vite builds emitting relative paths by not forcing an
        // absolute root during console/image builds. HTTP requests still need it.
        if (! app()->runningInConsole()) {
            URL::forceRootUrl($siteUrl);
        }

        if (str_starts_with($siteUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    protected static function cacheKey(string $key): string
    {
        return "settings.{$key}";
    }
}
