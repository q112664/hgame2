<?php

use App\Models\Category;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Sequence;
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
            ->where('resources.per_page', 8)
            ->where('resources.total', 0)
            ->has('resources.data', 0)
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
        'published_at' => now()->subDay(),
        'views_count' => 1250,
    ]);
    $tag = Tag::factory()->create([
        'name' => 'Romance',
        'slug' => 'romance',
    ]);
    $game->tags()->attach($tag);

    $platform = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $language = Language::factory()->create(['name' => 'Chinese', 'code' => 'zh']);
    $release = GameRelease::factory()->for($game)->create([
        'platform_id' => $platform->id,
        'language_id' => $language->id,
        'version' => '2.1',
        'published_at' => now()->subHour(),
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    Game::factory()->create(['title' => 'Unrelated Title', 'subtitle' => null]);

    $this->get(route('search', ['q' => $query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('search')
            ->where('query', $query)
            ->has('resources.data', 1)
            ->where('resources.data.0.id', 'senren-banka')
            ->where('resources.data.0.title', 'Senren Banka')
            ->where('resources.data.0.subtitle', 'A countryside love story')
            ->where('resources.data.0.category', 'Visual Novel')
            ->where('resources.data.0.developer', 'Yuzu Soft')
            ->where('resources.data.0.platforms', [
                ['name' => 'Windows', 'slug' => 'windows'],
            ])
            ->where('resources.data.0.languages', ['Chinese'])
            ->where('resources.data.0.version', '2.1')
            ->where('resources.data.0.tags', [
                ['name' => 'Romance', 'slug' => 'romance'],
            ])
            ->where('resources.data.0.views', 1250)
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
            ->has('resources.data', 0)
        );
});

test('search results are paginated eight per page', function () {
    Game::factory()
        ->count(9)
        ->state(new Sequence(fn (Sequence $sequence): array => [
            'slug' => "pagination-match-{$sequence->index}",
            'title' => "Pagination Match {$sequence->index}",
            'published_at' => now()->subMinutes($sequence->index),
        ]))
        ->create();

    $this->get(route('search', ['q' => 'Pagination Match']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.current_page', 1)
            ->where('resources.last_page', 2)
            ->where('resources.per_page', 8)
            ->where('resources.total', 9)
            ->has('resources.data', 8)
        );

    $this->get(route('search', ['q' => 'Pagination Match', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.current_page', 2)
            ->where('resources.last_page', 2)
            ->where('resources.per_page', 8)
            ->where('resources.total', 9)
            ->has('resources.data', 1)
        );
});
