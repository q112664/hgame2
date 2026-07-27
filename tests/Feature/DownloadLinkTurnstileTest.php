<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Setting;
use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeActiveDownloadLink(): GameDownloadLink
{
    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();

    return GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Mirror',
        'url' => 'https://cdn.example.com/game.zip',
        'is_active' => true,
    ]);
}

test('download jump page exposes the url when turnstile is disabled', function () {
    $link = makeActiveDownloadLink();

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('link.url', 'https://cdn.example.com/game.zip')
            ->where('link.requiresTurnstile', false)
        );
});

test('download jump page hides the url when turnstile is enabled', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    $link = makeActiveDownloadLink();

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('link.url', null)
            ->where('link.requiresTurnstile', true)
            ->where('link.host', 'cdn.example.com')
        );
});

test('download continue requires turnstile when enabled', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    $link = makeActiveDownloadLink();

    $this->from(route('download-links.show', $link))
        ->post(route('download-links.continue', $link))
        ->assertRedirect(route('download-links.show', $link))
        ->assertSessionHasErrors(Turnstile::FIELD);
});

test('download continue redirects away after verified turnstile', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $link = makeActiveDownloadLink();

    $this->post(route('download-links.continue', $link), [
        Turnstile::FIELD => 'valid-token',
    ])->assertRedirect('https://cdn.example.com/game.zip');
});

test('download continue works without turnstile when disabled', function () {
    $link = makeActiveDownloadLink();

    $this->post(route('download-links.continue', $link))
        ->assertRedirect('https://cdn.example.com/game.zip');
});
