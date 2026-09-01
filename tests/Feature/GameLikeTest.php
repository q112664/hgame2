<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot like a resource and are redirected to login', function () {
    $game = Game::factory()->create();

    $this->post(route('resources.like', $game->slug))
        ->assertRedirect(route('login'));
});

test('an authenticated user can like and unlike a published game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['likes_count' => 0]);

    $this->actingAs($user)
        ->from(route('resources.show', $game->slug))
        ->post(route('resources.like', $game->slug))
        ->assertRedirect(route('resources.show', $game->slug));

    expect($user->likedGames()->where('games.id', $game->id)->exists())->toBeTrue();
    expect($game->fresh()->likes_count)->toBe(1);

    $this->actingAs($user)
        ->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.isLiked', true)
            ->where('resource.likesCount', 1)
        );

    $this->actingAs($user)
        ->from(route('resources.show', $game->slug))
        ->post(route('resources.like', $game->slug))
        ->assertRedirect(route('resources.show', $game->slug));

    expect($user->likedGames()->where('games.id', $game->id)->exists())->toBeFalse();
    expect($game->fresh()->likes_count)->toBe(0);
});

test('likes count never goes below zero when unliking', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['likes_count' => 0]);

    $user->likedGames()->attach($game->id);

    $this->actingAs($user)
        ->post(route('resources.like', $game->slug))
        ->assertRedirect();

    expect($game->fresh()->likes_count)->toBe(0);
});

test('guests see isLiked false and the public likes count', function () {
    $game = Game::factory()->create(['likes_count' => 7, 'slug' => 'liked-game']);

    $this->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('resource.isLiked', false)
            ->where('resource.likesCount', 7)
        );
});
