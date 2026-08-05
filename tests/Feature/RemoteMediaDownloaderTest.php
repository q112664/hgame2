<?php

use App\Support\Media;
use App\Support\RemoteMediaDownloader;
use App\Support\SafeRemoteUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake(Media::diskName());
});

test('remote media downloader stores successful downloads', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Http::fake([
        'https://example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $path = app(RemoteMediaDownloader::class)->download('https://example.com/cover.png', 'games/covers');
    $stored = Storage::disk(Media::diskName())->get($path);

    expect($path)->toStartWith('games/covers/')
        ->and($path)->toEndWith('.webp')
        ->and((new finfo(FILEINFO_MIME_TYPE))->buffer($stored))->toBe('image/webp');
});

test('remote media downloader rejects oversized responses before storing them', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Http::fake([
        'https://example.com/*' => Http::response($png.str_repeat('x', 20 * 1024 * 1024), 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    expect(fn () => app(RemoteMediaDownloader::class)->download('https://example.com/large.png', 'games/covers'))
        ->toThrow(ValidationException::class);

    expect(Storage::disk(Media::diskName())->allFiles('games/covers'))->toBeEmpty();
});

test('remote media downloader rejects private network urls', function (string $url) {
    expect(fn () => SafeRemoteUrl::assert($url))
        ->toThrow(ValidationException::class);
})->with([
    'loopback ip' => ['http://127.0.0.1/cover.png'],
    'localhost host' => ['http://localhost/cover.png'],
    'link local metadata' => ['http://169.254.169.254/latest/meta-data'],
    'file scheme' => ['file:///etc/passwd'],
]);

test('safe remote url pins a public dns result for curl resolve', function () {
    $pin = SafeRemoteUrl::pin('https://example.com/cover.png');

    expect($pin['host'])->toBe('example.com')
        ->and($pin['port'])->toBe(443)
        ->and($pin['ip'])->not->toBeEmpty()
        ->and($pin['curl_resolve'][0])->toStartWith('example.com:443:')
        ->and(filter_var($pin['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))->not->toBeFalse();
});
