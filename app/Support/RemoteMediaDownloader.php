<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RemoteMediaDownloader
{
    private const int MaxBytes = 20 * 1024 * 1024;

    private const int MaxRedirects = 3;

    /** @var list<string> */
    private const array AllowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function download(string $url, string $directory): string
    {
        $response = $this->fetchPinned($url);

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

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extensions[$detected];

        if (Media::disk()->put($path, $contents, 'public') === false) {
            throw ValidationException::withMessages([
                'media' => "Failed to store media downloaded from [{$url}].",
            ]);
        }

        return $path;
    }

    private function fetchPinned(string $url): Response
    {
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= self::MaxRedirects; $redirect++) {
            $pin = SafeRemoteUrl::pin($currentUrl);

            try {
                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->withHeaders([
                        'User-Agent' => 'hgame-media-downloader/1.0',
                        'Accept' => 'image/*,*/*',
                    ])
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [
                            CURLOPT_RESOLVE => $pin['curl_resolve'],
                        ],
                    ])
                    ->get($currentUrl);
            } catch (ConnectionException $exception) {
                throw ValidationException::withMessages([
                    'media' => "Failed to download media from [{$currentUrl}]: {$exception->getMessage()}",
                ]);
            }

            if (! $response->redirect()) {
                return $response;
            }

            $location = $response->header('Location');

            if ($location === '') {
                throw ValidationException::withMessages([
                    'media' => "Media URL [{$currentUrl}] returned a redirect without a Location header.",
                ]);
            }

            $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
        }

        throw ValidationException::withMessages([
            'media' => "Media URL [{$url}] exceeded the redirect limit.",
        ]);
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        if (Str::startsWith($location, ['http://', 'https://'])) {
            return $location;
        }

        $parts = parse_url($currentUrl);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw ValidationException::withMessages([
                'media' => "Unable to resolve redirect from [{$currentUrl}].",
            ]);
        }

        $origin = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $basePath = isset($parts['path']) ? Str::beforeLast($parts['path'], '/') : '';

        return $origin.$basePath.'/'.$location;
    }
}
