<?php

namespace App\Support;

use App\Models\Setting;
use GdImage;
use Throwable;

/**
 * Turns the favicon uploaded in the admin into the shapes the web expects.
 *
 * The `<link rel="icon">` tag can point straight at the upload, but `/favicon.ico`
 * is served by nginx straight from `public/` and iOS insists on a 180x180 opaque
 * apple-touch icon. Both are generated here, on demand for the routes and onto disk
 * for the paths nginx answers itself.
 */
final class FaviconIcons
{
    /** ICO frames: browsers pick whichever they need. */
    private const IcoSizes = [16, 32, 48];

    /** Static files nginx serves without waking PHP. */
    private const Published = [
        'favicon.ico' => 'ico',
        'favicon-32.png' => 'png-32',
        'favicon-192.png' => 'png-192',
        'apple-touch-icon.png' => 'apple',
    ];

    /**
     * @return array{bytes: string, etag: string}|null
     */
    public function source(): ?array
    {
        $path = Setting::faviconPath();

        if ($path === null) {
            return null;
        }

        try {
            $bytes = Media::disk()->get($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return ['bytes' => $bytes, 'etag' => substr(sha1($bytes), 0, 16)];
    }

    public function ico(): ?string
    {
        $source = $this->source();

        return $source === null ? null : $this->buildIco($source['bytes']);
    }

    public function png(int $size): ?string
    {
        $source = $this->source();

        return $source === null ? null : $this->resizePng($source['bytes'], $size);
    }

    /**
     * iOS composites transparent pixels onto black, which wrecks artwork drawn on a
     * transparent canvas; flatten it onto a tint sampled from the icon instead.
     */
    public function appleTouch(): ?string
    {
        $source = $this->source();

        return $source === null ? null : $this->flattenedPng($source['bytes'], 180);
    }

    /**
     * Write the icons nginx serves from `public/`. Called on save and on boot, so a
     * rebuild never leaves the previous artwork behind.
     *
     * @return list<string> paths written
     */
    public function publish(?string $directory = null): array
    {
        $directory ??= public_path();
        $written = [];

        foreach (self::Published as $file => $kind) {
            $bytes = match ($kind) {
                'ico' => $this->ico(),
                'apple' => $this->appleTouch(),
                default => $this->png((int) str($kind)->after('png-')->value()),
            };

            if ($bytes === null || $bytes === '') {
                continue;
            }

            $target = rtrim($directory, '/').'/'.$file;

            if (@file_put_contents($target, $bytes) !== false) {
                $written[] = $target;
            }
        }

        return $written;
    }

    /** Never let icon publishing break a request or a container boot. */
    public function publishQuietly(): void
    {
        try {
            $this->publish();
        } catch (Throwable) {
            //
        }
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

                if ((($rgba >> 24) & 0x7F) > 100) {
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
