<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('visiting a resource page records a view once per session', function () {
    $game = Game::factory()->create([
        'slug' => 'viewed-game',
        'views_count' => 10,
    ]);

    $this->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.views', 11)
        );

    expect($game->fresh()->views_count)->toBe(11);

    $this->get(route('resources.downloads', $game->slug))->assertOk();
    $this->get(route('resources.screenshots', $game->slug))->assertOk();

    expect($game->fresh()->views_count)->toBe(11);
});

test('different sessions can each record a view for the same game', function () {
    $game = Game::factory()->create([
        'slug' => 'multi-session-game',
        'views_count' => 0,
    ]);

    $this->get(route('resources.details', $game->slug))->assertOk();
    expect($game->fresh()->views_count)->toBe(1);

    $this->flushSession();

    $this->get(route('resources.details', $game->slug))->assertOk();
    expect($game->fresh()->views_count)->toBe(2);
});

test('redirecting from the resource show route does not record a view', function () {
    $game = Game::factory()->create([
        'slug' => 'redirect-game',
        'views_count' => 5,
    ]);

    $this->get(route('resources.show', $game->slug))
        ->assertRedirect(route('resources.details', $game->slug));

    expect($game->fresh()->views_count)->toBe(5);
});
