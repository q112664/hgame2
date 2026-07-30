<?php

use App\Support\GameSource;

test('game source present returns null when empty', function () {
    expect(GameSource::present(null, null, null))->toBeNull()
        ->and(GameSource::present('  ', '', null))->toBeNull();
});

test('game source favicon uses the local dlsite icon', function () {
    $source = GameSource::present('DLsite', 'RJ01123456', null);

    expect($source)->not->toBeNull()
        ->and($source['name'])->toBe('DLsite')
        ->and($source['id'])->toBe('RJ01123456')
        ->and($source['faviconUrl'])->toBe('/images/sources/dlsite.ico')
        ->and(public_path('images/sources/dlsite.ico'))->toBeFile();
});

test('dlsite product urls use the local favicon', function () {
    $source = GameSource::present(
        null,
        'RJ01123456',
        'https://www.dlsite.com/maniax/work/=/product_id/RJ01123456.html',
    );

    expect($source['faviconUrl'])->toBe('/images/sources/dlsite.ico');
});

test('game source favicon falls back to host favicon for other shops', function () {
    $source = GameSource::present(
        'Custom Shop',
        'ABC-1',
        'https://shop.example.com/works/abc-1',
    );

    expect($source['faviconUrl'])->toContain('shop.example.com');
});
