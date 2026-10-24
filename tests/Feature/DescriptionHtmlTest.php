<?php

use App\Support\DescriptionHtml;

test('description images are deferred', function () {
    $html = DescriptionHtml::lazyImages(
        '<p>Intro</p><p><img src="https://img.eroga.me/screenshots/a.webp" width="1280"></p>',
    );

    expect($html)
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"')
        ->toContain('referrerpolicy="no-referrer"')
        ->toContain('src="https://img.eroga.me/screenshots/a.webp"')
        ->toContain('width="1280"');
});

test('existing image attributes are left alone', function () {
    $html = DescriptionHtml::lazyImages(
        '<img src="a.png" loading="eager" decoding="sync" referrerpolicy="origin">',
    );

    expect($html)->toBe(
        '<img src="a.png" loading="eager" decoding="sync" referrerpolicy="origin">',
    );
});

test('descriptions without images pass through untouched', function () {
    $html = '<p>No artwork here.</p>';

    expect(DescriptionHtml::lazyImages($html))->toBe($html);
});

test('rendered descriptions keep their markup and get lazy images', function () {
    $html = DescriptionHtml::render('<p>Intro</p><p><img src="https://img.eroga.me/x.webp"></p>');

    expect($html)
        ->toContain('<p>Intro</p>')
        ->toContain('loading="lazy"');
});
