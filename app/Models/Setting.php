<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

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
    }

    public static function siteUrl(): string
    {
        return rtrim((string) (static::get('site_url') ?: config('app.url')), '/');
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

        URL::forceRootUrl($siteUrl);
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function cacheKey(string $key): string
    {
        return "settings.{$key}";
    }
}
