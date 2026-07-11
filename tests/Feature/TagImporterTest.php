<?php

use App\Models\Tag;
use App\Support\TagImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tags can be imported from comma and newline separated input without duplicates', function () {
    $count = app(TagImporter::class)->import("Romance, Fantasy\nromance\r\nSlice of Life");

    expect($count)->toBe(3)
        ->and(Tag::query()->orderBy('slug')->pluck('slug')->all())
        ->toBe(['fantasy', 'romance', 'slice-of-life']);
});
