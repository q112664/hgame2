<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('home includes a popular section ordered by views', function () {
    $low = Game::factory()->create([
        'slug' => 'low-views',
        'title' => 'Low Views',
        'views_count' => 10,
    ]);
    $high = Game::factory()->create([
        'slug' => 'high-views',
        'title' => 'High Views',
        'views_count' => 500,
    ]);
    $mid = Game::factory()->create([
        'slug' => 'mid-views',
        'title' => 'Mid Views',
        'views_count' => 100,
    ]);
    Game::factory()->draft()->create([
        'slug' => 'draft-hot',
        'views_count' => 9999,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('popular', 3)
            ->where('popular.0.id', $high->slug)
            ->where('popular.1.id', $mid->slug)
            ->where('popular.2.id', $low->slug)
            ->has('resources')
        );
});

test('home popular section is empty when nothing is published', function () {
    Game::factory()->draft()->create(['views_count' => 100]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('popular', 0)
            ->has('resources', 0)
        );
});
