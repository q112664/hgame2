<?php

use App\Models\Tag;
use App\Support\TagImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tags can be imported from comma, space and newline separated input without duplicates', function () {
    $ids = app(TagImporter::class)->import("Romance, Fantasy\nromance  日常，奇幻");

    expect($ids)->toHaveCount(4)
        ->and(Tag::query()->pluck('name')->all())
        ->toEqualCanonicalizing(['Romance', 'Fantasy', '日常', '奇幻'])
        ->and(Tag::query()->whereKey($ids)->count())->toBe(4);
});

test('importing reuses existing tags matched by name or slug case-insensitively', function () {
    $existing = Tag::query()->create([
        'name' => 'Romance',
        'slug' => 'romance',
    ]);

    $ids = app(TagImporter::class)->import('romance, ROMANCE, Romance, Fantasy, fantasy');

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($existing->id)
        ->and(Tag::query()->count())->toBe(2);
});
