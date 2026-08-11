<?php

use App\Filament\Resources\Games\Schemas\GameForm;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('contributor admin labels use email as the unique reference', function () {
    $user = User::factory()->make([
        'name' => 'MirrorFox',
        'email' => 'mirrorfox@example.com',
    ]);

    expect(GameForm::contributorOptionLabel($user))
        ->toBe('MirrorFox · mirrorfox@example.com');

    $unnamed = User::factory()->make([
        'name' => '   ',
        'email' => 'only-email@example.com',
    ]);

    expect(GameForm::contributorOptionLabel($unnamed))
        ->toBe('only-email@example.com');
});

test('resource downloads expose contributor avatar and name on releases', function () {
    $game = Game::factory()->create();
    $contributor = User::factory()->create([
        'name' => 'MirrorFox',
        'avatar' => null,
    ]);
    $withContributor = GameRelease::factory()->for($game)->create([
        'user_id' => $contributor->id,
        'sort_order' => 0,
    ]);
    $withoutContributor = GameRelease::factory()->for($game)->create([
        'user_id' => null,
        'sort_order' => 1,
    ]);

    GameDownloadLink::factory()->for($withContributor, 'release')->create();
    GameDownloadLink::factory()->for($withoutContributor, 'release')->create();

    $this->get(route('resources.downloads', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('resource.releases.0.contributor.name', 'MirrorFox')
            ->where('resource.releases.0.contributor.avatar', null)
            ->where('resource.releases.1.contributor', null)
            ->where('resource.contributors.0.id', $contributor->id)
            ->where('resource.contributors.0.name', 'MirrorFox')
            ->missing('resource.releases.0.downloadLinks.0.contributor')
        );
});

test('resource hero exposes only the latest package contributor', function () {
    $game = Game::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $first = GameRelease::factory()->for($game)->create([
        'user_id' => $alice->id,
        'sort_order' => 0,
        'published_at' => now()->subDays(3),
    ]);
    $second = GameRelease::factory()->for($game)->create([
        'user_id' => $bob->id,
        'sort_order' => 1,
        'published_at' => now()->subDay(),
    ]);
    $duplicate = GameRelease::factory()->for($game)->create([
        'user_id' => $alice->id,
        'sort_order' => 2,
        'published_at' => now()->subDays(2),
    ]);

    GameDownloadLink::factory()->for($first, 'release')->create();
    GameDownloadLink::factory()->for($second, 'release')->create();
    GameDownloadLink::factory()->for($duplicate, 'release')->create();

    $this->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resource.contributors', 1)
            ->where('resource.contributors.0.id', $bob->id)
            ->where('resource.contributors.0.name', 'Bob')
        );
});

test('releases can belong to a site user as contributor', function () {
    $user = User::factory()->create();
    $release = GameRelease::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($release->contributor)->not->toBeNull()
        ->and($release->contributor->is($user))->toBeTrue()
        ->and($user->contributedReleases)->toHaveCount(1);
});

test('deleting a user clears the release assignment without deleting the release', function () {
    $user = User::factory()->create();
    $release = GameRelease::factory()->create([
        'user_id' => $user->id,
    ]);

    $user->delete();

    expect($release->fresh()->user_id)->toBeNull();
});
