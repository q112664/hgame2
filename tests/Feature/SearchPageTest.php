<?php

use App\Models\Category;
use App\Models\Game;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the search page starts empty without a query', function () {
    Game::factory()->create(['title' => 'Senren Banka']);

    $this->get(route('search'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('search')
            ->where('query', '')
            ->has('resources', 0)
        );
});

test('search matches title subtitle tags category platforms and languages', function (string $query) {
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $game = Game::factory()->create([
        'category_id' => $category->id,
        'slug' => 'senren-banka',
        'title' => 'Senren Banka',
        'subtitle' => 'A countryside love story',
        'developer' => 'Yuzu Soft',
    ]);
    $tag = Tag::factory()->create(['name' => 'Romance']);
    $game->tags()->attach($tag);

    $platform = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $language = Language::factory()->create(['name' => 'Chinese', 'code' => 'zh']);
    $release = GameRelease::factory()->for($game)->create();
    $release->platforms()->sync([$platform->id]);
    $release->languages()->sync([$language->id]);

    Game::factory()->create(['title' => 'Unrelated Title', 'subtitle' => null]);

    $this->get(route('search', ['q' => $query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('search')
            ->where('query', $query)
            ->has('resources', 1)
            ->where('resources.0.id', 'senren-banka')
            ->where('resources.0.title', 'Senren Banka')
            ->where('resources.0.subtitle', 'A countryside love story')
        );
})->with([
    'title' => ['Senren'],
    'subtitle' => ['countryside'],
    'tag' => ['Romance'],
    'category' => ['Visual Novel'],
    'platform' => ['Windows'],
    'language' => ['Chinese'],
]);

test('search ignores unpublished games', function () {
    Game::factory()->draft()->create([
        'title' => 'Hidden Draft',
        'subtitle' => 'Hidden subtitle',
    ]);

    $this->get(route('search', ['q' => 'Hidden']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('query', 'Hidden')
            ->has('resources', 0)
        );
});
