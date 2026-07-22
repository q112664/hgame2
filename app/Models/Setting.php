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
