<?php

use App\Models\Game;
use App\Models\GameRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot rate a resource and are redirected to login', function () {
    $game = Game::factory()->create();

    $this->post(route('resources.rating.store', $game->slug), ['score' => 5])
        ->assertRedirect(route('login'));

    $this->delete(route('resources.rating.destroy', $game->slug))
        ->assertRedirect(route('login'));
});

test('resource pages expose rating aggregates and the viewers score', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $game = Game::factory()->create();

    GameRating::factory()->for($game)->for($other)->create(['score' => 4]);
    GameRating::factory()->for($game)->for($user)->create(['score' => 5]);

    $this->actingAs($user)
        ->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.ratingAverage', 4.5)
            ->where('resource.ratingCount', 2)
            ->where('resource.userRating', 5)
        );

    auth()->logout();

    $this->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.ratingAverage', 4.5)
            ->where('resource.ratingCount', 2)
            ->where('resource.userRating', null)
        );
});

test('an authenticated user can rate update and clear a published game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $this->actingAs($user)
        ->from(route('resources.details', $game->slug))
        ->post(route('resources.rating.store', $game->slug), ['score' => 4])
        ->assertRedirect(route('resources.details', $game->slug));

    expect(GameRating::query()->where([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'score' => 4,
    ])->exists())->toBeTrue();

    $this->actingAs($user)
        ->from(route('resources.details', $game->slug))
        ->post(route('resources.rating.store', $game->slug), ['score' => 2])
        ->assertRedirect(route('resources.details', $game->slug));

    expect(GameRating::query()->where('user_id', $user->id)->where('game_id', $game->id)->value('score'))
        ->toBe(2);

    $this->actingAs($user)
        ->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.ratingAverage', 2)
            ->where('resource.ratingCount', 1)
            ->where('resource.userRating', 2)
        );

    $this->actingAs($user)
        ->from(route('resources.details', $game->slug))
        ->delete(route('resources.rating.destroy', $game->slug))
        ->assertRedirect(route('resources.details', $game->slug));

    expect(GameRating::query()->where('user_id', $user->id)->where('game_id', $game->id)->exists())
        ->toBeFalse();

    $this->actingAs($user)
        ->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.ratingAverage', null)
            ->where('resource.ratingCount', 0)
            ->where('resource.userRating', null)
        );
});

test('rating score must be an integer between 1 and 5', function (mixed $score) {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $this->actingAs($user)
        ->from(route('resources.details', $game->slug))
        ->post(route('resources.rating.store', $game->slug), ['score' => $score])
        ->assertSessionHasErrors('score');

    expect(GameRating::query()->count())->toBe(0);
})->with([
    'missing' => [null],
    'zero' => [0],
    'too high' => [6],
    'string' => ['great'],
]);
