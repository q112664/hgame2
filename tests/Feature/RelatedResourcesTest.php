<?php

use App\Actions\Games\ListRelatedGames;
use App\Models\Category;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('details tab includes up to four related resources', function () {
    $category = Category::factory()->create();

    $current = Game::factory()->create([
        'slug' => 'current-game',
        'category_id' => $category->id,
        'views_count' => 1,
    ]);

    foreach ([40, 30, 20, 10, 5] as $index => $views) {
        Game::factory()->create([
            'slug' => "related-{$index}",
            'category_id' => $category->id,
            'views_count' => $views,
        ]);
    }

    Game::factory()->draft()->create([
        'category_id' => $category->id,
        'views_count' => 999,
    ]);

    $this->get(route('resources.show', $current))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->has('related', 4)
            ->where('related.0.id', 'related-0')
            ->where('related.3.id', 'related-3')
        );
});

test('legacy downloads urls redirect instead of rendering a separate tab page', function () {
    $game = Game::factory()->create();

    $this->get(route('resources.downloads', $game))
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', $game).'#downloads');
});

test('list related games excludes the current game and prefers same category', function () {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $current = Game::factory()->create([
        'category_id' => $category->id,
        'views_count' => 1,
    ]);

    $sameCategory = Game::factory()->create([
        'slug' => 'same-category',
        'category_id' => $category->id,
        'views_count' => 10,
    ]);

    $other = Game::factory()->create([
        'slug' => 'other-category',
        'category_id' => $otherCategory->id,
        'views_count' => 100,
    ]);

    $related = app(ListRelatedGames::class)($current);
    $ids = collect($related)->pluck('id')->all();

    expect($ids)->toHaveCount(2)
        ->and($ids[0])->toBe($sameCategory->slug)
        ->and($ids)->toContain($other->slug)
        ->and($ids)->not->toContain($current->slug);
});
