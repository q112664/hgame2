<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MediaImageOptimizer
{
    public const int Quality = 80;

    public const int ScreenshotMaxDimension = 1920;

    public const int CoverMaxDimension = 1600;

    /**
     * @return array{
     *     binary: string,
     *     source_size: int,
     *     target_size: int,
     *     source_checksum: string,
     *     target_checksum: string,
     *     width: int,
     *     height: int
     * }
     */
    public function optimize(string $disk, string $path): array
    {
        $binary = Storage::disk($disk)->get($path);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException("Unable to read image [{$path}] from [{$disk}].");
        }

        return $this->optimizeBinary($binary, $path);
    }

    /**
     * @return array{
     *     binary: string,
     *     source_size: int,
     *     target_size: int,
     *     source_checksum: string,
     *     target_checksum: string,
     *     width: int,
     *     height: int
     * }
     */
    public function optimizeBinary(string $binary, string $path): array
    {
        if ($binary === '') {
            throw new RuntimeException("Image [{$path}] is empty.");
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException("Image [{$path}] could not be decoded by GD.");
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);

            [$targetWidth, $targetHeight] = $this->targetDimensions($path, $width, $height);
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($canvas === false) {
                throw new RuntimeException("Unable to allocate an image canvas for [{$path}].");
            }

            try {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

                if ($transparent === false) {
                    throw new RuntimeException("Unable to allocate transparency for [{$path}].");
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
                    throw new RuntimeException("Unable to resize image [{$path}].");
                }

                ob_start();
                $encodedSuccessfully = imagewebp($canvas, null, self::Quality);
                $optimized = ob_get_clean();

                if (! $encodedSuccessfully || $optimized === '') {
                    throw new RuntimeException("Unable to encode image [{$path}] as WebP.");
                }

                return [
                    'binary' => $optimized,
                    'source_size' => strlen($binary),
                    'target_size' => strlen($optimized),
                    'source_checksum' => hash('sha256', $binary),
                    'target_checksum' => hash('sha256', $optimized),
                    'width' => $targetWidth,
                    'height' => $targetHeight,
                ];
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }
    }

    public function targetPath(string $path, bool $useCollisionSuffix = false): string
    {
        $directory = str_replace('\\', '/', dirname($path));
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $suffix = $useCollisionSuffix ? '-optimized-'.substr(hash('sha256', $path), 0, 8) : '';
        $target = $filename.$suffix.'.webp';

        return $directory === '.' || $directory === ''
            ? $target
            : $directory.'/'.$target;
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     * @return array{0: int<1, max>, 1: int<1, max>}
     */
    private function targetDimensions(string $path, int $width, int $height): array
    {
        $maxDimension = str_starts_with($path, 'games/screenshots/')
            ? self::ScreenshotMaxDimension
            : self::CoverMaxDimension;
        $longestEdge = max($width, $height);

        if ($longestEdge <= $maxDimension) {
            return [$width, $height];
        }

        $scale = $maxDimension / $longestEdge;

        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);

        return [
            $this->positiveDimension($targetWidth, $path),
            $this->positiveDimension($targetHeight, $path),
        ];
    }

    /** @return int<1, max> */
    private function positiveDimension(int $dimension, string $path): int
    {
        if ($dimension < 1) {
            throw new RuntimeException("Image [{$path}] produced an invalid target dimension.");
        }

        return $dimension;
    }
}
