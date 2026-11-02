<?php

use App\Models\Game;
use App\Models\Tag;
use App\Support\TaxonomyDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    TaxonomyDirectory::forget();
});

test('the tag directory orders tags by how many published games use them', function () {
    $rare = Tag::factory()->create(['name' => 'Alpha Rare', 'slug' => 'alpha-rare']);
    $popular = Tag::factory()->create(['name' => 'Zulu Popular', 'slug' => 'zulu-popular']);
    $middling = Tag::factory()->create(['name' => 'Mike Middling', 'slug' => 'mike-middling']);

    Game::factory()->count(3)->create()->each(
        fn (Game $game) => $game->tags()->attach($popular),
    );
    Game::factory()->count(2)->create()->each(
        fn (Game $game) => $game->tags()->attach($middling),
    );
    Game::factory()->create()->tags()->attach($rare);

    // A draft game must not inflate the count.
    Game::factory()->draft()->create()->tags()->attach($rare);

    $tags = TaxonomyDirectory::tagsIndex();

    expect($tags)
        ->toHaveCount(3)
        ->and(array_column($tags, 'name'))->toBe([
            'Zulu Popular',
            'Mike Middling',
            'Alpha Rare',
        ])
        ->and(array_column($tags, 'count'))->toBe([3, 2, 1]);
});

test('tags with the same count fall back to alphabetical order', function () {
    $zebra = Tag::factory()->create(['name' => 'Zebra', 'slug' => 'zebra']);
    $apple = Tag::factory()->create(['name' => 'Apple', 'slug' => 'apple']);

    foreach ([$zebra, $apple] as $tag) {
        Game::factory()->create()->tags()->attach($tag);
    }

    expect(array_column(TaxonomyDirectory::tagsIndex(), 'name'))
        ->toBe(['Apple', 'Zebra']);
});
