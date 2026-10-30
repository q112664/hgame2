<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Media;
use GdImage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the classic icon paths from whatever favicon is currently uploaded.
 *
 * The `<link rel="icon">` tag already pointed at the media upload, but `/favicon.ico`,
 * `/favicon-192.png` and `/apple-touch-icon.png` stayed as static files from the repo,
 * so pinning a tab, a bookmark or an iOS home screen still showed the old artwork.
 * These routes keep every path in sync with the admin setting.
 */
final class FaviconController
{
    /** ICO frames: browsers pick whichever they need. */
    private const IcoSizes = [16, 32, 48];

    public function ico(): Response
    {
        $source = $this->source();

        if ($source === null) {
            abort(404);
        }

        return $this->cacheable($this->buildIco($source['bytes']), 'image/x-icon', $source['etag'].'-ico');
    }

    public function png(int $size): Response
    {
        $source = $this->source();

        if ($source === null) {
            abort(404);
        }

        return $this->cacheable(
            $this->resizePng($source['bytes'], $size),
            'image/png',
            $source['etag'].'-'.$size,
        );
    }

    /**
     * iOS composites transparent pixels onto black, which wrecks the corners of
     * artwork drawn on a transparent canvas; flatten onto a matching tint instead.
     */
    public function appleTouch(): Response
    {
        $source = $this->source();

        if ($source === null) {
            abort(404);
        }

        return $this->cacheable(
            $this->flattenedPng($source['bytes'], 180),
            'image/png',
            $source['etag'].'-apple',
        );
    }

    /**
     * @return array{bytes: string, etag: string}|null
     */
    private function source(): ?array
    {
        $path = Setting::faviconPath();

        if ($path === null) {
            return null;
        }

        try {
            $bytes = Media::disk()->get($path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return ['bytes' => $bytes, 'etag' => substr(sha1($bytes), 0, 16)];
    }

    private function cacheable(string $body, string $contentType, string $etag): Response
    {
        $response = new Response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.$etag.'"',
        ]);

        $response->setLastModified(now()->subMinute());
        $response->isNotModified(request());

        return $response;
    }

    private function buildIco(string $bytes): string
    {
        $image = $this->decode($bytes);

        if ($image === null) {
            return $bytes;
        }

        $frames = [];

        foreach (self::IcoSizes as $size) {
            $frames[$size] = $this->encodePng($this->scale($image, $size));
        }

        $count = count($frames);
        $header = pack('vvv', 0, 1, $count);
        $offset = 6 + (16 * $count);
        $directory = '';
        $data = '';

        foreach ($frames as $size => $png) {
            $directory .= pack(
                'CCCCvvVV',
                $size >= 256 ? 0 : $size,
                $size >= 256 ? 0 : $size,
                0,
                0,
                1,
                32,
                strlen($png),
                $offset,
            );

            $offset += strlen($png);
            $data .= $png;
        }

        return $header.$directory.$data;
    }

    private function resizePng(string $bytes, int $size): string
    {
        $image = $this->decode($bytes);

        if ($image === null) {
            return $bytes;
        }

        return $this->encodePng($this->scale($image, $size));
    }

    private function flattenedPng(string $bytes, int $size): string
    {
        $image = $this->decode($bytes);

        if ($image === null) {
            return $bytes;
        }

        $scaled = $this->scale($image, $size);
        $canvas = imagecreatetruecolor($size, $size);

        if ($canvas === false) {
            return $this->encodePng($scaled);
        }

        [$red, $green, $blue] = $this->averageColor($image);
        $background = imagecolorallocate($canvas, $red, $green, $blue);

        if ($background !== false) {
            imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, 0, 0, 0, 0, $size, $size);

        return $this->encodePng($canvas);
    }

    private function decode(string $bytes): ?GdImage
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        return $image instanceof GdImage ? $image : null;
    }

    private function scale(GdImage $image, int $size): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $square = imagecreatetruecolor($size, $size);

        if ($square === false) {
            return $image;
        }

        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, imagecolorallocatealpha($square, 0, 0, 0, 127));

        // Fit the artwork inside the square without distorting it.
        $scale = min($size / max($width, 1), $size / max($height, 1));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        imagecopyresampled(
            $square,
            $image,
            (int) floor(($size - $targetWidth) / 2),
            (int) floor(($size - $targetHeight) / 2),
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        return $square;
    }

    private function encodePng(GdImage $image): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        return is_string($png) ? $png : '';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function averageColor(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) floor(max($width, $height) / 32));

        $red = $green = $blue = $samples = 0;

        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                $rgba = imagecolorat($image, $x, $y);

                if ($rgba === false) {
                    continue;
                }

                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha > 100) {
                    continue;
                }

                $red += ($rgba >> 16) & 0xFF;
                $green += ($rgba >> 8) & 0xFF;
                $blue += $rgba & 0xFF;
                $samples++;
            }
        }

        if ($samples === 0) {
            return [17, 17, 19];
        }

        // Slightly darker than the artwork so the icon edge still reads.
        return [
            (int) max(0, ($red / $samples) * 0.45),
            (int) max(0, ($green / $samples) * 0.45),
            (int) max(0, ($blue / $samples) * 0.45),
        ];
    }
}
