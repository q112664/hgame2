<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('published download links open an intermediate jump page', function () {
    $game = Game::factory()->create([
        'slug' => 'senren-banka',
        'title' => 'Senren Banka',
    ]);
    $release = GameRelease::factory()->for($game)->create([
        'title' => 'Windows package',
        'version' => '1.0',
        'file_size' => '5.4 GB',
    ]);
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Baidu Netdisk',
        'url' => 'https://pan.baidu.com/s/example',
        'is_active' => true,
    ]);

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('download-links/show')
            ->where('resource.id', 'senren-banka')
            ->where('resource.title', 'Senren Banka')
            ->has('resource.thumbnail')
            ->missing('release')
            ->where('link.id', $link->id)
            ->where('link.label', 'Baidu Netdisk')
            ->where('link.url', 'https://pan.baidu.com/s/example')
            ->where('link.host', 'pan.baidu.com')
            ->where('link.requiresTurnstile', false)
        );
});

test('inactive download links are not available', function () {
    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://example.com/file.zip',
        'is_active' => false,
    ]);

    // Model forces is_active true on save; mark inactive after create.
    $link->forceFill(['is_active' => false])->saveQuietly();

    $this->get(route('download-links.show', $link))
        ->assertNotFound();
});

test('download links for draft games are not available', function () {
    $game = Game::factory()->draft()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://example.com/file.zip',
    ]);

    $this->get(route('download-links.show', $link))
        ->assertNotFound();
});
