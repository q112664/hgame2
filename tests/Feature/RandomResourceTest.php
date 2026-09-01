<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('random resource redirects to a published game details page', function () {
    $game = Game::factory()->create(['slug' => 'lucky-draw']);
    Game::factory()->draft()->create(['slug' => 'hidden-draft']);

    $this->get(route('resources.random'))
        ->assertRedirect(route('resources.show', $game))
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('random resource redirects to the resources index when none are published', function () {
    Game::factory()->draft()->create();

    $this->get(route('resources.random'))
        ->assertRedirect(route('resources.index'));
});

test('random resource only selects published games', function () {
    $published = Game::factory()->create(['slug' => 'published-pick']);
    Game::factory()->draft()->create(['slug' => 'draft-skip']);

    $this->get(route('resources.random'))
        ->assertRedirect(route('resources.show', $published));
});
