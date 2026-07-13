<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view favorites and are redirected to login', function () {
    $this->get(route('favorites.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot toggle favorites and are redirected to login', function () {
    $game = Game::factory()->create();

    $this->post(route('resources.favorite', $game->slug))
        ->assertRedirect(route('login'));
});

test('an authenticated user can favorite and unfavorite a published game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $this->actingAs($user)
        ->from(route('resources.details', $game->slug))
        ->post(route('resources.favorite', $game->slug))
        ->assertRedirect(route('resources.details', $game->slug));

    expect($user->favoritedGames()->where('games.id', $game->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.isFavorited', true)
        );

    $this->actingAs($user)
        ->from(route('resources.details', $game->slug))
        ->post(route('resources.favorite', $game->slug))
        ->assertRedirect(route('resources.details', $game->slug));

    expect($user->favoritedGames()->where('games.id', $game->id)->exists())->toBeFalse();
});

test('favorites page lists the users favorited games newest first', function () {
    $user = User::factory()->create();
    $older = Game::factory()->create(['title' => 'Older Favorite', 'slug' => 'older-favorite']);
    $newer = Game::factory()->create(['title' => 'Newer Favorite', 'slug' => 'newer-favorite']);

    $user->favoritedGames()->attach($older->id, ['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
    $user->favoritedGames()->attach($newer->id, ['created_at' => now(), 'updated_at' => now()]);

    Game::factory()->create(['title' => 'Not Favorited']);

    $this->actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('favorites')
            ->has('resources', 2)
            ->where('resources.0.id', 'newer-favorite')
            ->where('resources.1.id', 'older-favorite')
        );
});

test('favorites page ignores unpublished favorited games', function () {
    $user = User::factory()->create();
    $draft = Game::factory()->draft()->create(['title' => 'Draft Favorite']);
    $user->favoritedGames()->attach($draft->id);

    $this->actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('favorites')
            ->has('resources', 0)
        );
});

test('favorites page notifies when favorited game downloads are updated', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['slug' => 'updated-favorite']);
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $release = GameRelease::factory()->for($game)->create([
        'title' => 'New package',
        'version' => '2.0',
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    expect($game->fresh()->downloads_updated_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('downloadUpdateCount', 1)
            ->where('resources.0.id', 'updated-favorite')
            ->where('resources.0.hasDownloadUpdate', true)
        );

    $this->actingAs($user)
        ->get(route('resources.downloads', $game->slug))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('downloadUpdateCount', 0)
            ->where('resources.0.hasDownloadUpdate', false)
        );
});
