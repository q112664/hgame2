<?php

use App\Models\Setting;
use App\Support\Media;
use Illuminate\Support\Facades\Storage;

test('media disk defaults from configuration', function () {
    expect(Media::diskName())->toBe('public');
});

test('media url returns absolute and rooted paths unchanged', function () {
    expect(Media::url('https://cdn.example/cover.jpg'))->toBe('https://cdn.example/cover.jpg')
        ->and(Media::url('/storage/games/covers/a.jpg'))->toBe('/storage/games/covers/a.jpg')
        ->and(Media::url(null))->toBe('')
        ->and(Media::url(''))->toBe('');
});

test('media url uses the site url on the public disk', function () {
    Storage::fake(Media::diskName());
    Setting::applySiteUrlToConfig('http://hgame.test');

    expect(Media::url('games/covers/demo.jpg'))->toBe('http://hgame.test/storage/games/covers/demo.jpg');
});

test('media url uses the configured s3 disk url', function () {
    config([
        'filesystems.media' => 's3',
        'filesystems.disks.s3' => [
            'driver' => 's3',
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'media',
            'url' => 'https://cdn.example',
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => false,
            'report' => false,
            'visibility' => 'public',
        ],
    ]);

    Storage::fake('s3');

    expect(Media::url('games/covers/demo.jpg'))->toContain('games/covers/demo.jpg');
});
