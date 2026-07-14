<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Media
{
    public static function diskName(): string
    {
        return (string) config('filesystems.media', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function url(?string $path): string
    {
        if (blank($path)) {
            return '';
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        if (self::diskName() === 'public') {
            return rtrim(Setting::siteUrl(), '/').'/storage/'.$path;
        }

        return self::disk()->url($path);
    }
}
