<?php

use App\Filament\Pages\ManageSiteSettings;
use App\GameStatus;
use App\Models\Game;
use App\Models\Setting;
use App\Models\User;
use App\Support\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.indexnow.org/*' => Http::response('', 202),
    ]);
});

function enableIndexNow(string $key = 'indexnowkey123'): void
{
    Setting::setBoolean('indexnow_enabled', true);
    Setting::set('indexnow_key', $key);
}

test('administrators can save indexnow settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'indexnow_enabled' => true,
            'indexnow_key' => 'bingindexnowkey1',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::boolean('indexnow_enabled'))->toBeTrue()
        ->and(Setting::get('indexnow_key'))->toBe('bingindexnowkey1');
});

test('disabling indexnow keeps the stored api key', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'indexnow_enabled' => true,
            'indexnow_key' => 'keepindexnowkey1',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('indexnow_key'))->toBe('keepindexnowkey1');

    $page
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'indexnow_enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::boolean('indexnow_enabled'))->toBeFalse()
        ->and(Setting::get('indexnow_key'))->toBe('keepindexnowkey1');
});

test('the indexnow key file is served only when enabled with a matching key', function () {
    $key = 'indexnowkey123';

    $this->get('/'.$key.'.txt')->assertNotFound();

    enableIndexNow($key);

    $this->get('/'.$key.'.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee($key, false);

    $this->get('/wrongkey1.txt')->assertNotFound();

    Setting::setBoolean('indexnow_enabled', false);

    $this->get('/'.$key.'.txt')->assertNotFound();
});

test('publishing a game submits its url to indexnow', function () {
    enableIndexNow();

    $game = Game::factory()->create([
        'slug' => 'new-listed-game',
        'status' => GameStatus::Published,
        'published_at' => now()->subMinute(),
    ]);

    Http::assertSent(function ($request) use ($game): bool {
        return $request->url() === IndexNow::Endpoint
            && $request['host'] === IndexNow::host()
            && $request['key'] === 'indexnowkey123'
            && $request['urlList'] === [IndexNow::gameUrl($game)];
    });
});

test('draft games are not submitted to indexnow', function () {
    enableIndexNow();

    Game::factory()->create([
        'status' => GameStatus::Draft,
        'published_at' => null,
    ]);

    Http::assertNothingSent();
});

test('publishing a draft later submits to indexnow', function () {
    enableIndexNow();

    $game = Game::factory()->create([
        'slug' => 'later-published',
        'status' => GameStatus::Draft,
        'published_at' => null,
    ]);

    Http::assertNothingSent();

    $game->update([
        'status' => GameStatus::Published,
        'published_at' => now(),
    ]);

    Http::assertSent(fn ($request): bool => $request['urlList'] === [IndexNow::gameUrl($game)]);
});

test('editing a published game without a listing change does not submit to indexnow', function () {
    enableIndexNow();

    $game = Game::factory()->create([
        'title' => 'Original title',
        'status' => GameStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    Http::assertSentCount(1);

    $game->update(['title' => 'Revised title']);

    Http::assertSentCount(1);
});

test('marking downloads as updated submits a listed game to indexnow', function () {
    $game = Game::factory()->create([
        'slug' => 'download-updated',
        'status' => GameStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    Http::assertNothingSent();

    enableIndexNow();
    $game->touchDownloadsUpdatedAt();

    Http::assertSent(fn ($request): bool => $request['urlList'] === [IndexNow::gameUrl($game)]);
});

test('indexnow is skipped when disabled', function () {
    Setting::setBoolean('indexnow_enabled', false);
    Setting::set('indexnow_key', 'indexnowkey123');

    Game::factory()->create([
        'status' => GameStatus::Published,
        'published_at' => now(),
    ]);

    Http::assertNothingSent();
});
