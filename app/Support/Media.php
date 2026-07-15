<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class Media
{
    /** @return list<string> */
    public static function diskNames(): array
    {
        return ['public', 's3'];
    }

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

    /**
     * Delete a media object from every known media disk.
     *
     * @return bool True when every disk that held the object deleted it successfully.
     */
    public static function delete(?string $path): bool
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://', '/'])) {
            return true;
        }

        $failed = [];

        foreach (self::diskNames() as $disk) {
            try {
                if (! Storage::disk($disk)->exists($path)) {
                    continue;
                }

                $deleted = Storage::disk($disk)->delete($path);

                if ($deleted === false) {
                    $failed[] = $disk;
                } elseif (Storage::disk($disk)->exists($path)) {
                    $failed[] = $disk;
                }
            } catch (Throwable $exception) {
                $failed[] = $disk;

                Log::warning('Failed to delete media object.', [
                    'path' => $path,
                    'disk' => $disk,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($failed !== []) {
            Log::warning('Media object cleanup incomplete.', [
                'path' => $path,
                'failed_disks' => $failed,
            ]);

            return false;
        }

        return true;
    }
}
