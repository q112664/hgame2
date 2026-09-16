<?php

use App\Models\Category;
use App\Models\Game;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('search matches regardless of query casing', function (string $query) {
    Game::factory()->create([
        'slug' => 'senren-banka',
        'title' => 'Senren Banka',
        'subtitle' => 'A countryside love story',
        'developer' => 'Yuzu Soft',
    ]);
    Game::factory()->create(['title' => 'Unrelated Title']);

    $this->get(route('search', ['q' => $query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resources.data', 1)
            ->where('resources.data.0.id', 'senren-banka')
        );
})->with([
    'title lower' => ['senren'],
    'title upper' => ['SENREN'],
    'title mixed' => ['sEnReN'],
    'subtitle upper' => ['COUNTRYSIDE'],
    'developer upper' => ['YUZU'],
]);

test('search matches taxonomy names regardless of casing', function (string $query) {
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $game = Game::factory()->create([
        'slug' => 'taxonomy-match',
        'title' => 'Taxonomy Match',
        'category_id' => $category->id,
    ]);
    $tag = Tag::factory()->create(['name' => 'Romance', 'slug' => 'romance']);
    $game->tags()->attach($tag);

    $this->get(route('search', ['q' => $query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resources.data', 1)
            ->where('resources.data.0.id', 'taxonomy-match')
        );
})->with([
    'category upper' => ['VISUAL NOVEL'],
    'tag upper' => ['ROMANCE'],
]);

test('search compiles to a case-insensitive like on postgres', function () {
    // Plain LIKE is case-sensitive on PostgreSQL (production), while SQLite
    // (local + tests) is not. Assert the emitted SQL instead of relying on the
    // local driver to catch a regression.
    $sql = Game::on('pgsql')->matchingSearch('Senren')->toSql();

    expect($sql)->toContain('ilike');
    expect(preg_match('/(?<!i)like /', $sql))->toBe(0);
});
