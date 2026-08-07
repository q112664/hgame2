<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generates card-sized cover thumbnails as WebP.
 *
 * Card grid at max-w-7xl with up to 4 columns renders covers around ~290–400px CSS.
 * Default 560px balances 3–4 column retina quality with smaller payloads than full originals.
 * Max width and quality are configurable in admin settings.
 */
final class MediaThumbnail
{
    public const int MaxWidth = Setting::DEFAULT_COVER_THUMBNAIL_MAX_WIDTH;

    public const int Quality = Setting::DEFAULT_COVER_THUMBNAIL_QUALITY;

    public static function maxWidth(): int
    {
        return Setting::coverThumbnailMaxWidth();
    }

    public static function quality(): int
    {
        return Setting::coverThumbnailQuality();
    }

    public static function pathFor(string $path): string
    {
        $directory = str_replace('\\', '/', dirname($path));
        $filename = pathinfo($path, PATHINFO_FILENAME);

        if ($directory === '.' || $directory === '') {
            return 'thumbs/'.$filename.'.webp';
        }

        return $directory.'/thumbs/'.$filename.'.webp';
    }

    public static function isManagedPath(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        return ! Str::startsWith($path, ['http://', 'https://', '/']);
    }

    /**
     * Public card URL for a cover path.
     *
     * Must never probe or read object storage: on R2/S3 each exists()/get() is a
     * network round-trip, and doing that per card on catalog/home pages stalls TTFB.
     * Thumbnails are written on cover save and via media:generate-cover-thumbnails.
     */
    public static function url(?string $path): string
    {
        if (blank($path)) {
            return '';
        }

        if (! self::isManagedPath($path)) {
            return Media::url($path);
        }

        return Media::url(self::pathFor($path));
    }

    /**
     * Create or refresh a WebP thumbnail for a stored media path.
     *
     * Always materializes a WebP at the deterministic thumbnail path so card URLs
     * never need a remote existence check (even when the source is already small).
     *
     * @return string|null Thumbnail path when created.
     */
    public static function generate(string $path, ?int $maxWidth = null, ?int $quality = null): ?string
    {
        try {
            return self::generateOrFail($path, $maxWidth, $quality);
        } catch (Throwable $exception) {
            Log::warning('Failed to generate media thumbnail.', [
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ensure a thumbnail exists on the active disk and has a local rollback copy.
     *
     * @throws Throwable When a required source or mirrored write cannot be verified.
     */
    public static function ensureReady(string $path, bool $force = false): ?string
    {
        if (! self::isManagedPath($path)) {
            return null;
        }

        $thumbnailPath = self::pathFor($path);
        $diskName = Media::diskName();

        if (! $force && $diskName === 'r2' && Storage::disk('r2')->exists($thumbnailPath)) {
            self::syncR2ThumbnailToLocal($thumbnailPath);

            return $thumbnailPath;
        }

        if (! $force && $diskName !== 'r2' && Storage::disk($diskName)->exists($thumbnailPath)) {
            return $thumbnailPath;
        }

        return self::generateOrFail($path);
    }

    /**
     * Generate a thumbnail and mirror it to local storage when R2 is active.
     *
     * @throws Throwable When the source cannot be read or either required write fails.
     */
    public static function generateOrFail(
        string $path,
        ?int $maxWidth = null,
        ?int $quality = null,
    ): ?string {
        if (! self::isManagedPath($path)) {
            return null;
        }

        $diskName = Media::diskName();
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            throw new RuntimeException("Source media [{$path}] does not exist.");
        }

        $maxWidth ??= self::maxWidth();
        $quality ??= self::quality();
        $thumbnailPath = self::pathFor($path);
        $binary = $disk->get($path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException("Source media [{$path}] could not be read.");
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException("Source media [{$path}] is not a supported image.");
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            if ($maxWidth < 1 || $quality < 0 || $quality > 100) {
                throw new RuntimeException("Thumbnail settings for [{$path}] are invalid.");
            }

            $targetWidth = $width > $maxWidth ? $maxWidth : $width;
            $targetHeight = $width > $maxWidth
                ? (int) max(1, (int) round($height * ($targetWidth / $width)))
                : $height;
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($canvas === false) {
                throw new RuntimeException("Thumbnail canvas for [{$path}] could not be created.");
            }

            try {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

                if ($transparent === false) {
                    throw new RuntimeException("Thumbnail transparency for [{$path}] could not be allocated.");
                }

                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

                if (! imagecopyresampled(
                    $canvas,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $width,
                    $height,
                )) {
                    throw new RuntimeException("Thumbnail resize for [{$path}] failed.");
                }

                ob_start();
                $encodedSuccessfully = imagewebp($canvas, null, $quality);
                $encoded = ob_get_clean();

                if (! $encodedSuccessfully || $encoded === '') {
                    throw new RuntimeException("Thumbnail encoding for [{$path}] failed.");
                }

                self::writeThumbnail($thumbnailPath, $encoded, $diskName);

                return $thumbnailPath;
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }
    }

    private static function writeThumbnail(string $path, string $binary, string $diskName): void
    {
        $diskNames = $diskName === 'r2' ? ['r2', 'public'] : [$diskName];
        $snapshots = [];

        foreach ($diskNames as $name) {
            $snapshots[$name] = self::snapshot($name, $path);
        }

        try {
            foreach ($diskNames as $name) {
                self::writeAndVerify($name, $path, $binary);
            }
        } catch (Throwable $exception) {
            foreach ($snapshots as $name => $snapshot) {
                self::restore($name, $path, $snapshot);
            }

            throw $exception;
        }
    }

    private static function syncR2ThumbnailToLocal(string $path): void
    {
        $binary = Storage::disk('r2')->get($path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException("R2 thumbnail [{$path}] could not be read.");
        }

        self::writeAndVerify('public', $path, $binary);
    }

    /** @return array{exists: bool, binary: string|null} */
    private static function snapshot(string $diskName, string $path): array
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return ['exists' => false, 'binary' => null];
        }

        $binary = $disk->get($path);

        if (! is_string($binary)) {
            throw new RuntimeException("Thumbnail [{$path}] could not be backed up on [{$diskName}].");
        }

        return ['exists' => true, 'binary' => $binary];
    }

    /** @param array{exists: bool, binary: string|null} $snapshot */
    private static function restore(string $diskName, string $path, array $snapshot): void
    {
        try {
            $disk = Storage::disk($diskName);

            if (! $snapshot['exists']) {
                if ($disk->exists($path) && $disk->delete($path) === false) {
                    throw new RuntimeException("Thumbnail [{$path}] could not be removed from [{$diskName}].");
                }

                return;
            }

            if ($snapshot['binary'] === null || $disk->put($path, $snapshot['binary'], 'public') === false) {
                throw new RuntimeException("Thumbnail [{$path}] could not be restored on [{$diskName}].");
            }

            $stored = $disk->get($path);

            if (
                ! is_string($stored)
                || strlen($stored) !== strlen($snapshot['binary'])
                || ! hash_equals(hash('sha256', $snapshot['binary']), hash('sha256', $stored))
            ) {
                throw new RuntimeException("Restored thumbnail [{$path}] failed verification on [{$diskName}].");
            }
        } catch (Throwable $exception) {
            Log::error('Failed to restore media thumbnail after an interrupted write.', [
                'path' => $path,
                'disk' => $diskName,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private static function writeAndVerify(string $diskName, string $path, string $binary): void
    {
        $snapshot = self::snapshot($diskName, $path);
        /** @var Filesystem $disk */
        $disk = Storage::disk($diskName);

        try {
            if ($disk->put($path, $binary, 'public') === false) {
                throw new RuntimeException("Unable to store thumbnail [{$path}] on [{$diskName}].");
            }

            $stored = $disk->get($path);

            if (
                ! is_string($stored)
                || strlen($stored) !== strlen($binary)
                || ! hash_equals(hash('sha256', $binary), hash('sha256', $stored))
            ) {
                throw new RuntimeException("Thumbnail [{$path}] failed verification on [{$diskName}].");
            }
        } catch (Throwable $exception) {
            self::restore($diskName, $path, $snapshot);

            throw $exception;
        }
    }

    public static function deleteFor(?string $path): bool
    {
        if (! self::isManagedPath($path)) {
            return true;
        }

        return Media::delete(self::pathFor((string) $path));
    }
}
