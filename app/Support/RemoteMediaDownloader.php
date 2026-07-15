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

        $contentLength = (string) $response->header('Content-Length');

        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > self::MaxBytes) {
            throw ValidationException::withMessages([
                'media' => "Media from [{$url}] is empty or exceeds the 20MB limit.",
            ]);
        }

        $temporary = tmpfile();

        if ($temporary === false) {
            throw ValidationException::withMessages([
                'media' => "Unable to buffer media downloaded from [{$url}].",
            ]);
        }

        try {
            $bytes = $this->bufferResponse($response, $temporary, $url);
            $temporaryPath = stream_get_meta_data($temporary)['uri'] ?? null;
            $detected = is_string($temporaryPath)
                ? ((new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) ?: '')
                : '';

            if ($bytes === 0 || ! in_array($detected, self::AllowedMimeTypes, true)) {
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
            rewind($temporary);

            if (Media::disk()->put($path, $temporary, 'public') === false) {
                throw ValidationException::withMessages([
                    'media' => "Failed to store media downloaded from [{$url}].",
                ]);
            }

            return $path;
        } finally {
            fclose($temporary);
        }
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
                        'stream' => true,
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

    /**
     * @param  resource  $target
     */
    private function bufferResponse(Response $response, mixed $target, string $url): int
    {
        $body = $response->toPsrResponse()->getBody();
        $bytes = 0;

        while (! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '') {
                break;
            }

            $bytes += strlen($chunk);

            if ($bytes > self::MaxBytes) {
                throw ValidationException::withMessages([
                    'media' => "Media from [{$url}] is empty or exceeds the 20MB limit.",
                ]);
            }

            $offset = 0;
            $length = strlen($chunk);

            while ($offset < $length) {
                $written = fwrite($target, substr($chunk, $offset));

                if ($written === false || $written === 0) {
                    throw ValidationException::withMessages([
                        'media' => "Unable to buffer media downloaded from [{$url}].",
                    ]);
                }

                $offset += $written;
            }
        }

        return $bytes;
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
