<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public static function url(?string $path): string
    {
        if (blank($path)) {
            return '';
        }

        if (! self::isManagedPath($path)) {
            return Media::url($path);
        }

        $thumbnailPath = self::pathFor($path);

        if (Media::disk()->exists($thumbnailPath)) {
            return Media::url($thumbnailPath);
        }

        $generated = self::generate($path);

        if ($generated !== null) {
            return Media::url($generated);
        }

        return Media::url($path);
    }

    /**
     * Create or refresh a WebP thumbnail for a stored media path.
     *
     * @return string|null Thumbnail path when created or already small enough to skip.
     */
    public static function generate(string $path, ?int $maxWidth = null, ?int $quality = null): ?string
    {
        if (! self::isManagedPath($path) || ! Media::disk()->exists($path)) {
            return null;
        }

        $maxWidth ??= self::maxWidth();
        $quality ??= self::quality();
        $thumbnailPath = self::pathFor($path);

        try {
            $binary = Media::disk()->get($path);

            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $source = @imagecreatefromstring($binary);

            if ($source === false) {
                return null;
            }

            try {
                $width = imagesx($source);
                $height = imagesy($source);

                if ($width < 1 || $height < 1) {
                    return null;
                }

                if ($width <= $maxWidth) {
                    return null;
                }

                $targetWidth = $maxWidth;
                $targetHeight = (int) max(1, (int) round($height * ($targetWidth / $width)));

                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

                if ($canvas === false) {
                    return null;
                }

                try {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

                    imagecopyresampled(
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
                    );

                    ob_start();
                    imagewebp($canvas, null, $quality);
                    $encoded = ob_get_clean();

                    if (! is_string($encoded) || $encoded === '') {
                        return null;
                    }

                    if (Media::disk()->put($thumbnailPath, $encoded, 'public') === false) {
                        return null;
                    }

                    return $thumbnailPath;
                } finally {
                    imagedestroy($canvas);
                }
            } finally {
                imagedestroy($source);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to generate media thumbnail.', [
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
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
