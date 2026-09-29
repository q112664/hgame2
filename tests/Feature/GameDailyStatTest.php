<?php

use App\Actions\Games\RecordGameView;
use App\Models\Game;
use App\Models\GameDailyStat;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Setting;
use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeDailyStatDownloadLink(Game $game): GameDownloadLink
{
    $release = GameRelease::factory()->for($game)->create();

    return GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Mirror',
        'url' => 'https://cdn.example.com/daily.zip',
        'is_active' => true,
    ]);
}

test('opening a resource page records a view against today', function () {
    $game = Game::factory()->create([
        'slug' => 'daily-view-game',
        'views_count' => 0,
    ]);

    $this->get(route('resources.show', $game->slug))->assertOk();

    $stat = GameDailyStat::query()->sole();

    expect($stat->game_id)->toBe($game->id)
        ->and($stat->date)->toBe(today()->toDateString())
        ->and($stat->views)->toBe(1)
        ->and($stat->downloads)->toBe(0)
        ->and($game->fresh()->views_count)->toBe(1);
});

test('repeated visits on the same day accumulate into a single row', function () {
    $game = Game::factory()->create([
        'slug' => 'daily-repeat-game',
        'views_count' => 0,
    ]);

    $this->get(route('resources.show', $game->slug))->assertOk();
    $this->get(route('resources.show', $game->slug))->assertOk();
    $this->get(route('resources.show', $game->slug))->assertOk();

    $stats = GameDailyStat::query()->where('game_id', $game->id)->get();

    expect($stats)->toHaveCount(1)
        ->and($stats->first()->views)->toBe(3)
        ->and($game->fresh()->views_count)->toBe(3);
});

test('every game keeps its own daily row', function () {
    $first = Game::factory()->create(['slug' => 'daily-game-one']);
    $second = Game::factory()->create(['slug' => 'daily-game-two']);

    $this->get(route('resources.show', $first->slug))->assertOk();
    $this->get(route('resources.show', $second->slug))->assertOk();

    expect(GameDailyStat::query()->count())->toBe(2)
        ->and(GameDailyStat::query()->where('game_id', $first->id)->value('views'))->toBe(1)
        ->and(GameDailyStat::query()->where('game_id', $second->id)->value('views'))->toBe(1);
});

test('tab navigation within a resource records no daily view', function () {
    $game = Game::factory()->create([
        'slug' => 'daily-tab-game',
        'views_count' => 0,
    ]);

    // The same guard that protects the lifetime counter: switching tabs is not
    // a fresh read of the page.
    $request = Request::create(
        route('resources.show', ['resource' => $game->slug, 'tab' => 'downloads']),
        'GET',
        server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_RESOURCE_TAB_NAV' => '1',
        ],
    );

    app(RecordGameView::class)($request, $game);

    expect(GameDailyStat::query()->count())->toBe(0)
        ->and($game->fresh()->views_count)->toBe(0);
});

test('continuing a download records a daily download', function () {
    $game = Game::factory()->create(['slug' => 'daily-download-game']);
    $link = makeDailyStatDownloadLink($game);

    $this->post(route('download-links.continue', $link))
        ->assertRedirect('https://cdn.example.com/daily.zip');

    $stat = GameDailyStat::query()->sole();

    expect($stat->game_id)->toBe($game->id)
        ->and($stat->date)->toBe(today()->toDateString())
        ->and($stat->downloads)->toBe(1)
        ->and($stat->views)->toBe(0);
});

test('a download blocked by turnstile records nothing for the day', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    $game = Game::factory()->create([
        'slug' => 'daily-turnstile-game',
        'downloads_count' => 0,
    ]);
    $link = makeDailyStatDownloadLink($game);

    $this->from(route('download-links.show', $link))
        ->post(route('download-links.continue', $link))
        ->assertSessionHasErrors(Turnstile::FIELD);

    expect(GameDailyStat::query()->count())->toBe(0)
        ->and($game->fresh()->downloads_count)->toBe(0);
});

test('verified turnstile continue records a daily download', function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
    Setting::setBoolean('turnstile_download_enabled', true);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $game = Game::factory()->create(['slug' => 'daily-verified-game']);
    $link = makeDailyStatDownloadLink($game);

    $this->post(route('download-links.continue', $link), [
        Turnstile::FIELD => 'valid-token',
    ])->assertRedirect('https://cdn.example.com/daily.zip');

    expect(GameDailyStat::query()->sole()->downloads)->toBe(1);
});

test('recording daily traffic does not bump game updated_at', function () {
    $frozen = now()->subDay()->startOfSecond();

    $game = Game::factory()->create([
        'slug' => 'daily-lastmod-game',
        'created_at' => $frozen,
        'updated_at' => $frozen,
    ]);

    $game->forceFill(['updated_at' => $frozen])->saveQuietly();

    $this->get(route('resources.show', $game->slug))->assertOk();

    $fresh = $game->fresh();

    expect($fresh->updated_at?->equalTo($frozen))->toBeTrue()
        ->and(GameDailyStat::query()->sole()->views)->toBe(1);
});

test('daily stats are removed with their game', function () {
    $game = Game::factory()->create(['slug' => 'daily-deleted-game']);

    $this->get(route('resources.show', $game->slug))->assertOk();

    expect(GameDailyStat::query()->count())->toBe(1);

    $game->delete();

    expect(GameDailyStat::query()->count())->toBe(0);
});
