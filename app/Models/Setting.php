<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

        if (in_array($key, [
            'media_disk',
            'aws_access_key_id',
            'aws_secret_access_key',
            'aws_default_region',
            'aws_bucket',
            'aws_url',
            'aws_endpoint',
            'aws_use_path_style_endpoint',
        ], true)) {
            static::applyMediaConfigToConfig();
        }
    }

    public static function setEncrypted(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        static::set($key, Crypt::encryptString($value));
    }

    public static function getDecrypted(string $key, ?string $default = null): ?string
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }

    public static function siteUrl(): string
    {
        return rtrim((string) (static::get('site_url') ?: config('app.url')), '/');
    }

    public static function mediaDisk(): string
    {
        $disk = static::get('media_disk');

        if (in_array($disk, ['public', 's3'], true)) {
            return $disk;
        }

        return (string) config('filesystems.media', 'public');
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

        // Keep Wayfinder/Vite builds emitting relative paths. Absolute roots are
        // only needed for HTTP requests (and queue mail uses config('app.url')).
        if (! app()->runningInConsole()) {
            URL::forceRootUrl($siteUrl);
        }
    }

    public static function applyMediaConfigToConfig(): void
    {
        if (! static::tableReady()) {
            return;
        }

        $disk = static::get('media_disk');

        if (in_array($disk, ['public', 's3'], true)) {
            config(['filesystems.media' => $disk]);
        }

        $s3 = [];

        if (filled($key = static::get('aws_access_key_id'))) {
            $s3['filesystems.disks.s3.key'] = $key;
        }

        if (filled($secret = static::getDecrypted('aws_secret_access_key'))) {
            $s3['filesystems.disks.s3.secret'] = $secret;
        }

        if (filled($region = static::get('aws_default_region'))) {
            $s3['filesystems.disks.s3.region'] = $region;
        }

        if (filled($bucket = static::get('aws_bucket'))) {
            $s3['filesystems.disks.s3.bucket'] = $bucket;
        }

        $url = static::get('aws_url');
        if ($url !== null) {
            $s3['filesystems.disks.s3.url'] = $url !== '' ? $url : null;
        }

        $endpoint = static::get('aws_endpoint');
        if ($endpoint !== null) {
            $s3['filesystems.disks.s3.endpoint'] = $endpoint !== '' ? $endpoint : null;
        }

        $pathStyle = static::get('aws_use_path_style_endpoint');
        if ($pathStyle !== null) {
            $s3['filesystems.disks.s3.use_path_style_endpoint'] = filter_var($pathStyle, FILTER_VALIDATE_BOOLEAN);
        }

        if ($s3 !== []) {
            config($s3);
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
