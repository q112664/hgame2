<?php

use App\Models\Category;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('search finds the title when the words are given in another order', function () {
    $match = Game::factory()->create(['title' => 'Room 404: Erotic Massage']);
    Game::factory()->create(['title' => 'Unrelated Title']);

    $this->get(route('search', ['q' => 'room massage']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.total', 1)
            ->where('resources.data.0.id', $match->slug)
        );
});

test('each word may match a different field', function () {
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $match = Game::factory()->create([
        'category_id' => $category->id,
        'title' => 'Senren Banka',
        'developer' => 'Yuzu Soft',
    ]);
    Game::factory()->create(['title' => 'Another Game', 'developer' => 'Other Dev']);

    // "visual" hits the category, "senren" hits the title.
    $this->get(route('search', ['q' => 'visual senren']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.total', 1)
            ->where('resources.data.0.id', $match->slug)
        );
});

test('every word in the query must match somewhere', function () {
    Game::factory()->create(['title' => 'Senren Banka']);

    $this->get(route('search', ['q' => 'senren zzzzzz']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resources.total', 0));
});

test('search matches the original store name and slug', function () {
    $bySource = Game::factory()->create([
        'title' => 'English Title',
        'source_name' => 'ノベルゲーム',
    ]);
    $bySlug = Game::factory()->create([
        'title' => 'Another Title',
        'slug' => 'senren-banka-slug',
    ]);

    $this->get(route('search', ['q' => 'ノベルゲーム']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.total', 1)
            ->where('resources.data.0.id', $bySource->slug)
        );

    $this->get(route('search', ['q' => 'senren-banka']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.total', 1)
            ->where('resources.data.0.id', $bySlug->slug)
        );
});

test('the trigram fallback tolerates typos on postgres', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Trigram similarity is a PostgreSQL feature.');
    }

    $match = Game::factory()->create(['title' => 'RE:D Cherish!']);

    $this->get(route('search', ['q' => 'cherissh']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.total', 1)
            ->where('resources.data.0.id', $match->slug)
        );
});
