<?php

use App\Models\ResourceSource;
use App\Support\GameSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('game source present returns null when empty', function () {
    expect(GameSource::present(null, null, null))->toBeNull()
        ->and(GameSource::present('  ', '', null))->toBeNull();
});

test('game source favicon uses the library dlsite icon', function () {
    $source = GameSource::present('DLsite', 'RJ01123456', null);

    expect($source)->not->toBeNull()
        ->and($source['name'])->toBe('DLsite')
        ->and($source['id'])->toBe('RJ01123456')
        ->and($source['faviconUrl'])->toBe('/images/sources/dlsite.ico')
        ->and(public_path('images/sources/dlsite.ico'))->toBeFile();
});

test('dlsite product urls use the library favicon', function () {
    $source = GameSource::present(
        null,
        'RJ01123456',
        'https://www.dlsite.com/maniax/work/=/product_id/RJ01123456.html',
    );

    expect($source['faviconUrl'])->toBe('/images/sources/dlsite.ico');
});

test('game source favicon uses the library steam icon', function () {
    $source = GameSource::present('Steam', '1234560', null);

    expect($source)->not->toBeNull()
        ->and($source['name'])->toBe('Steam')
        ->and($source['id'])->toBe('1234560')
        ->and($source['faviconUrl'])->toBe('/images/sources/steam.ico')
        ->and(public_path('images/sources/steam.ico'))->toBeFile();
});

test('steam store product urls use the library favicon', function () {
    $source = GameSource::present(
        null,
        '1234560',
        'https://store.steampowered.com/app/1234560/Example/',
    );

    expect($source['faviconUrl'])->toBe('/images/sources/steam.ico');
});

test('known sources list library entries with icons', function () {
    $known = GameSource::known();

    expect($known)->toHaveCount(2)
        ->and(collect($known)->pluck('name')->all())->toBe(['DLsite', 'Steam'])
        ->and($known[0]['favicon_url'])->toBe('/images/sources/dlsite.ico')
        ->and($known[1]['favicon_url'])->toBe('/images/sources/steam.ico');
});

test('custom library sources supply reusable icons', function () {
    ResourceSource::factory()->create([
        'name' => 'Booth',
        'slug' => 'booth',
        'icon_path' => '/images/sources/dlsite.ico',
        'host_hint' => 'booth.pm',
        'sort_order' => 10,
    ]);

    GameSource::forgetCache();

    $source = GameSource::present('Booth', 'item-1', null);

    expect(GameSource::options())->toHaveKey('Booth')
        ->and($source['faviconUrl'])->toBe('/images/sources/dlsite.ico')
        ->and(GameSource::present(
            null,
            'item-1',
            'https://booth.pm/en/items/1',
        )['faviconUrl'])->toBe('/images/sources/dlsite.ico');
});

test('game source favicon falls back to host favicon for unknown shops', function () {
    $source = GameSource::present(
        'Custom Shop',
        'ABC-1',
        'https://shop.example.com/works/abc-1',
    );

    expect($source['faviconUrl'])->toContain('shop.example.com');
});
