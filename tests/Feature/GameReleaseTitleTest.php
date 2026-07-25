<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('resource downloads expose the release title above description content', function () {
    $game = Game::factory()->create();
    $platform = Platform::factory()->create(['name' => 'Windows']);
    $language = Language::factory()->create(['name' => 'Chinese']);
    $release = GameRelease::factory()->for($game)->create([
        'platform_id' => $platform->id,
        'language_id' => $language->id,
        'title' => 'Steam version',
        'description' => '<p>Patch notes</p>',
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $this->get(route('resources.downloads', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('resource.releases.0.title', 'Steam version')
            ->where(
                'resource.releases.0.description',
                fn (string $description): bool => str_contains($description, '<p>Patch notes</p>') && ! str_contains($description, '<script>'),
            )
        );
});
