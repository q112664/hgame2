<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\User;
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
            ->where('profile.id', $user->id)
            ->where('profile.name', 'MirrorFox')
            ->where('profile.avatar', null)
            ->has('resources.data', 1)
            ->where('resources.data.0.id', 'contributed-game')
            ->where('resources.data.0.title', 'Contributed Game')
            ->where('resources.total', 1)
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
            ->has('resources.data', 0)
            ->where('resources.total', 0)
        );
});

test('resource downloads expose contributor id for profile links', function () {
    $user = User::factory()->create([
        'name' => 'Linker',
    ]);
    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create([
        'user_id' => $user->id,
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $this->get(route('resources.downloads', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.releases.0.contributor.id', $user->id)
            ->where('resource.releases.0.contributor.name', 'Linker')
        );
});
