<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('home and resource pages expose platform name and slug for icon mapping', function () {
    $game = Game::factory()->create(['slug' => 'multi-platform-game']);
    $windows = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $ios = Platform::factory()->create(['name' => 'iOS', 'slug' => 'ios']);
    $android = Platform::factory()->create(['name' => 'Android', 'slug' => 'android']);
    $language = Language::factory()->create(['name' => 'Chinese', 'code' => 'zh']);

    $release = GameRelease::factory()->for($game)->create([
        'platform_id' => $windows->id,
        'language_id' => $language->id,
        'title' => 'Multi platform package',
    ]);
    $release->platforms()->sync([$windows->id, $ios->id, $android->id]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $expectedPlatforms = [
        ['name' => 'Windows', 'slug' => 'windows'],
        ['name' => 'iOS', 'slug' => 'ios'],
        ['name' => 'Android', 'slug' => 'android'],
    ];

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.0.id', $game->slug)
            ->where('resources.0.platforms', $expectedPlatforms)
        );

    $this->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.platforms', $expectedPlatforms)
            ->where('resource.releases.0.platforms', $expectedPlatforms)
            ->where('resource.hasDownloads', true)
        );
});

test('future-dated releases are hidden from resource pages', function () {
    $game = Game::factory()->create(['slug' => 'scheduled-release-game']);
    $platform = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $language = Language::factory()->create(['name' => 'Chinese', 'code' => 'zh']);

    $release = GameRelease::factory()->for($game)->create([
        'platform_id' => $platform->id,
        'language_id' => $language->id,
        'published_at' => now()->addDay(),
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $this->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.platforms', [])
            ->where('resource.releases', [])
            ->where('resource.hasDownloads', false)
        );
});
