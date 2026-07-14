<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RemoteMediaDownloader
{
    private const int MaxBytes = 20 * 1024 * 1024;

    /** @var list<string> */
    private const array AllowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function download(string $url, string $directory): string
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->withHeaders([
                    'User-Agent' => 'hgame-media-downloader/1.0',
                    'Accept' => 'image/*,*/*',
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages([
                'media' => "Failed to download media from [{$url}]: {$exception->getMessage()}",
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'media' => "Failed to download media from [{$url}] (HTTP {$response->status()}).",
            ]);
        }

        $contents = $response->body();

        if ($contents === '' || strlen($contents) > self::MaxBytes) {
            throw ValidationException::withMessages([
                'media' => "Media from [{$url}] is empty or exceeds the 20MB limit.",
            ]);
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';

        if (! in_array($detected, self::AllowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'media' => "Media from [{$url}] must be a JPEG, PNG, WebP, or GIF image.",
            ]);
        }

        $extension = match ($detected) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(Media::diskName())->put($path, $contents, 'public');

        return $path;
    }
}
