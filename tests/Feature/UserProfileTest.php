<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('user profiles show avatar name and contributed resources', function () {
    $user = User::factory()->create([
        'name' => 'MirrorFox',
        'avatar' => null,
    ]);
    $other = User::factory()->create();

    $contributed = Game::factory()->create([
        'title' => 'Contributed Game',
        'slug' => 'contributed-game',
    ]);
    $release = GameRelease::factory()->for($contributed)->create([
        'user_id' => $user->id,
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $otherGame = Game::factory()->create([
        'title' => 'Other Game',
        'slug' => 'other-game',
    ]);
    $otherRelease = GameRelease::factory()->for($otherGame)->create([
        'user_id' => $other->id,
    ]);
    GameDownloadLink::factory()->for($otherRelease, 'release')->create();

    $draft = Game::factory()->draft()->create();
    $draftRelease = GameRelease::factory()->for($draft)->create([
        'user_id' => $user->id,
    ]);
    GameDownloadLink::factory()->for($draftRelease, 'release')->create();

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->where('profile.slug', $user->slug)
            ->missing('profile.id')
            ->where('profile.name', 'MirrorFox')
            ->where('profile.avatar', null)
            ->where('activeTab', 'resources')
            ->where('isOwner', false)
            ->where('resourcesCount', 1)
            ->where('favoritesCount', 0)
            ->has('resources.data', 1)
            ->where('resources.data.0.id', 'contributed-game')
            ->where('resources.data.0.title', 'Contributed Game')
            ->where('resources.total', 1)
            ->where('favorites', null)
        );
});

test('user profiles can be empty when the user has no published contributions', function () {
    $user = User::factory()->create([
        'name' => 'Newbie',
    ]);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->where('profile.name', 'Newbie')
            ->where('activeTab', 'resources')
            ->has('resources.data', 0)
            ->where('resources.total', 0)
            ->where('resourcesCount', 0)
        );
});

test('user profiles expose a public favorites tab', function () {
    $user = User::factory()->create(['name' => 'Collector']);
    $viewer = User::factory()->create();
    $older = Game::factory()->create([
        'title' => 'Older Favorite',
        'slug' => 'older-favorite',
    ]);
    $newer = Game::factory()->create([
        'title' => 'Newer Favorite',
        'slug' => 'newer-favorite',
    ]);

    $user->favoritedGames()->attach($older->id, [
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
    $user->favoritedGames()->attach($newer->id, [
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('users.favorites', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->where('activeTab', 'favorites')
            ->where('isOwner', false)
            ->where('favoritesCount', 2)
        );

    $this->actingAs($viewer)
        ->get(route('users.favorites', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->where('activeTab', 'favorites')
            ->where('isOwner', false)
            ->where('favoritesCount', 2)
            ->where('resources', null)
            ->has('favorites.data', 2)
            ->where('favorites.data.0.id', 'newer-favorite')
            ->where('favorites.data.1.id', 'older-favorite')
            ->where('favorites.data.0.hasDownloadUpdate', false)
            ->where('downloadUpdateCount', 0)
        );
});

test('owners see download update badges on their favorites tab', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['slug' => 'updated-on-profile']);
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $game->touchDownloadsUpdatedAt();

    $this->actingAs($user)
        ->get(route('users.favorites', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTab', 'favorites')
            ->where('isOwner', true)
            ->where('downloadUpdateCount', 1)
            ->where('favorites.data.0.id', 'updated-on-profile')
            ->where('favorites.data.0.hasDownloadUpdate', true)
        );
});

test('resource downloads expose contributor slugs for profile links', function () {
    $user = User::factory()->create([
        'name' => 'Linker',
    ]);
    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create([
        'user_id' => $user->id,
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $this->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.releases.0.contributor.slug', $user->slug)
            ->where('resource.releases.0.contributor.name', 'Linker')
            ->missing('resource.releases.0.contributor.id')
        );
});

test('numeric user profile urls redirect to the public slug', function () {
    $user = User::factory()->create();

    $this->get('/users/'.$user->id)
        ->assertRedirect(route('users.show', $user));

    $this->get('/users/'.$user->id.'/favorites')
        ->assertRedirect(route('users.favorites', $user));

    expect(route('users.show', $user, absolute: false))
        ->toBe('/users/'.$user->slug)
        ->not->toContain('/users/'.$user->id);
});

test('the profile grids show a placeholder skeleton while a page is loading', function () {
    $filesystem = app(Filesystem::class);

    $skeleton = $filesystem->get(resource_path('js/components/site/resource-grid-skeleton.tsx'));
    $page = $filesystem->get(resource_path('js/pages/users/show.tsx'));

    // Cards keep their silhouette so swapping the grid for placeholders does not jump.
    expect($skeleton)
        ->toContain('aspect-[16/10]')
        ->toContain('animate-pulse')
        ->toContain('aria-hidden');

    expect($page)
        ->toContain('import { ResourceGridSkeleton }')
        ->toContain('<ResourceGridSkeleton')
        // Short partial reloads skip the skeleton instead of blinking.
        ->toContain('setTimeout(() => setIsLoading(true), 120)')
        ->toContain("router.on('finish'")
        // A new page of results starts back at the top of the list.
        ->toContain('scrollIntoView');
});

test('a hidden favorites tab is private to the owner', function () {
    $user = User::factory()->create(['hide_favorites' => true]);

    $this->get(route('users.favorites', $user))
        ->assertRedirect(route('users.show', $user));

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewFavorites', false)
            ->where('canViewResources', true)
            // Counts are zeroed so a private total never leaks.
            ->where('favoritesCount', 0)
        );

    $this->actingAs($user)
        ->get(route('users.favorites', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewFavorites', true)
            ->where('hiddenFavorites', true)
        );
});

test('a hidden resources tab sends visitors to the other section', function () {
    $user = User::factory()->create(['hide_resources' => true]);

    $this->get(route('users.show', $user))
        ->assertRedirect(route('users.favorites', $user));

    $this->get(route('users.favorites', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewResources', false)
            ->where('resourcesCount', 0)
        );
});

test('a profile with both sections hidden is not reachable for visitors', function () {
    $user = User::factory()->create([
        'hide_resources' => true,
        'hide_favorites' => true,
    ]);

    $this->get(route('users.show', $user))->assertNotFound();
    $this->get(route('users.favorites', $user))->assertNotFound();
});
