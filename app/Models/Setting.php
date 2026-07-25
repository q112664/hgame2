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

    public static function ratingsEnabled(): bool
    {
        $value = static::get('ratings_enabled');

        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setRatingsEnabled(bool $enabled): void
    {
        static::set('ratings_enabled', $enabled ? '1' : '0');
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

    /**
     * @return list<array{label: string, url: string, icon: string|null, open_in_new_tab: bool, match: 'exact'|'prefix'|'none'}>
     */
    public static function defaultNavigationMenu(): array
    {
        return [
            [
                'label' => 'Home',
                'url' => '/',
                'icon' => null,
                'open_in_new_tab' => false,
                'match' => 'exact',
            ],
            [
                'label' => 'Resources',
                'url' => '/resources',
                'icon' => null,
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
     * @param  list<mixed>  $items
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

    public static function isValidNavigationMenuUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
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
