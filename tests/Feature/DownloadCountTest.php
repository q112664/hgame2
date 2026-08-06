<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Setting;
use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeDownloadLinkWithCount(int $downloadsCount = 0): GameDownloadLink
{
    $game = Game::factory()->create([
        'downloads_count' => $downloadsCount,
    ]);
    $release = GameRelease::factory()->for($game)->create();

    return GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Mirror',
        'url' => 'https://cdn.example.com/game.zip',
        'is_active' => true,
    ]);
}

test('continuing a download increments the game downloads count', function () {
    $link = makeDownloadLinkWithCount(5);
    $game = $link->release->game;

    $this->post(route('download-links.continue', $link))
        ->assertRedirect('https://cdn.example.com/game.zip');

    expect($game->fresh()->downloads_count)->toBe(6);
});

test('viewing the download jump page does not increment downloads', function () {
    $link = makeDownloadLinkWithCount(3);
    $game = $link->release->game;

    $this->get(route('download-links.show', $link))->assertOk();

    expect($game->fresh()->downloads_count)->toBe(3);
});

test('recording a download does not bump game updated_at', function () {
    $link = makeDownloadLinkWithCount(0);
    $game = $link->release->game;

    $frozen = now()->subDay()->startOfSecond();
    // Freeze after link creation — saved links touch downloads_updated_at on the game.
    $game->forceFill([
        'created_at' => $frozen,
        'updated_at' => $frozen,
    ])->saveQuietly();

    $this->post(route('download-links.continue', $link))
        ->assertRedirect('https://cdn.example.com/game.zip');

    $fresh = $game->fresh();

    expect($fresh->downloads_count)->toBe(1)
        ->and($fresh->updated_at?->equalTo($frozen))->toBeTrue();
});

test('failed turnstile verification does not increment downloads', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    $link = makeDownloadLinkWithCount(2);
    $game = $link->release->game;

    $this->from(route('download-links.show', $link))
        ->post(route('download-links.continue', $link))
        ->assertRedirect(route('download-links.show', $link))
        ->assertSessionHasErrors(Turnstile::FIELD);

    expect($game->fresh()->downloads_count)->toBe(2);
});

test('verified turnstile continue increments downloads', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $link = makeDownloadLinkWithCount(4);
    $game = $link->release->game;

    $this->post(route('download-links.continue', $link), [
        Turnstile::FIELD => 'valid-token',
    ])->assertRedirect('https://cdn.example.com/game.zip');

    expect($game->fresh()->downloads_count)->toBe(5);
});
