<?php

use App\Actions\Stats\BuildDailyTrafficSeries;
use App\Actions\Stats\BuildTopResourcesQuery;
use App\Filament\Widgets\DailyTrafficChart;
use App\Filament\Widgets\RecentUsersTable;
use App\Filament\Widgets\SiteStatsOverview;
use App\Filament\Widgets\TopResourcesTable;
use App\Models\Game;
use App\Models\GameDailyStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Pull the chart's data out of the JS string literal Filament embeds it in.
 */
function dailyChartPayload(string $html): array
{
    $marker = "cachedData: JSON.parse('";
    $start = strpos($html, $marker);

    expect($start)->not->toBeFalse();

    $start += strlen($marker);
    $end = strpos($html, "')", $start);

    expect($end)->not->toBeFalse();

    // The only escape the literal uses on quotes is \u0022, so undoing that is
    // enough to leave valid JSON behind.
    return json_decode(
        str_replace('\u0022', '"', substr($html, $start, $end - $start)),
        associative: true,
    );
}

test('the traffic series covers every day of the window', function () {
    $series = app(BuildDailyTrafficSeries::class)();

    expect($series['dates'])->toHaveCount(30)
        ->and($series['views'])->toHaveCount(30)
        ->and($series['downloads'])->toHaveCount(30)
        ->and($series['dates'][0])->toBe(today()->subDays(29)->toDateString())
        ->and($series['dates'][29])->toBe(today()->toDateString());
});

test('quiet days keep their slot instead of dropping out of the axis', function () {
    $game = Game::factory()->create();

    GameDailyStat::factory()->create([
        'game_id' => $game->id,
        'date' => today()->toDateString(),
        'views' => 7,
        'downloads' => 2,
    ]);
    GameDailyStat::factory()->create([
        'game_id' => $game->id,
        'date' => today()->subDays(3)->toDateString(),
        'views' => 5,
        'downloads' => 1,
    ]);

    $series = app(BuildDailyTrafficSeries::class)();

    expect($series['views'][29])->toBe(7)
        ->and($series['views'][26])->toBe(5)
        // Nothing happened four days ago; the day is still on the axis.
        ->and($series['views'][25])->toBe(0)
        ->and($series['downloads'][29])->toBe(2)
        ->and(array_sum($series['views']))->toBe(12);
});

test('the series adds up every game on the same day', function () {
    $now = today()->toDateString();

    GameDailyStat::factory()->create(['date' => $now, 'views' => 4, 'downloads' => 1]);
    GameDailyStat::factory()->create(['date' => $now, 'views' => 6, 'downloads' => 3]);

    $series = app(BuildDailyTrafficSeries::class)();

    expect($series['views'][29])->toBe(10)
        ->and($series['downloads'][29])->toBe(4);
});

test('the series ignores days older than its window', function () {
    GameDailyStat::factory()->create([
        'date' => today()->subDays(45)->toDateString(),
        'views' => 99,
        'downloads' => 99,
    ]);

    $series = app(BuildDailyTrafficSeries::class)();

    expect(array_sum($series['views']))->toBe(0)
        ->and(array_sum($series['downloads']))->toBe(0);
});

test('top resources weights a download above a view', function () {
    $crowdOfViews = Game::factory()->create(['slug' => 'hot-views']);
    $fewDownloads = Game::factory()->create(['slug' => 'hot-downloads']);

    GameDailyStat::factory()->create([
        'game_id' => $crowdOfViews->id,
        'date' => today()->toDateString(),
        'views' => 100,
        'downloads' => 0,
    ]);
    GameDailyStat::factory()->create([
        'game_id' => $fewDownloads->id,
        'date' => today()->toDateString(),
        'views' => 0,
        'downloads' => 30,
    ]);

    // 30 downloads outrank 100 views because a download carries five times the
    // weight; this is the whole point of the weighting.
    expect(app(BuildTopResourcesQuery::class)()->pluck('slug')->all())
        ->toBe(['hot-downloads', 'hot-views']);
});

test('top resources sums the whole window per game', function () {
    $spread = Game::factory()->create(['slug' => 'steady-traffic']);
    $spike = Game::factory()->create(['slug' => 'single-spike']);

    foreach ([0, 1, 2] as $daysAgo) {
        GameDailyStat::factory()->create([
            'game_id' => $spread->id,
            'date' => today()->subDays($daysAgo)->toDateString(),
            'views' => 40,
        ]);
    }

    GameDailyStat::factory()->create([
        'game_id' => $spike->id,
        'date' => today()->toDateString(),
        'views' => 100,
    ]);

    expect(app(BuildTopResourcesQuery::class)()->pluck('slug')->all())
        ->toBe(['steady-traffic', 'single-spike']);
});

test('top resources leaves out unpublished games and stale traffic', function () {
    $published = Game::factory()->create(['slug' => 'published-hot']);
    $draft = Game::factory()->draft()->create(['slug' => 'draft-hot']);
    $staleTraffic = Game::factory()->create(['slug' => 'stale-hot']);

    GameDailyStat::factory()->create([
        'game_id' => $published->id,
        'date' => today()->toDateString(),
        'views' => 10,
    ]);
    GameDailyStat::factory()->create([
        'game_id' => $draft->id,
        'date' => today()->toDateString(),
        'views' => 999,
    ]);
    GameDailyStat::factory()->create([
        'game_id' => $staleTraffic->id,
        'date' => today()->subDays(30)->toDateString(),
        'views' => 500,
    ]);

    // An admin previewing a draft is not an audience, and traffic from a month
    // ago is not this week's ranking.
    expect(app(BuildTopResourcesQuery::class)()->pluck('slug')->all())
        ->toBe(['published-hot']);
});

test('the chart hands the browser two series of thirty daily points', function () {
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->create();

    GameDailyStat::factory()->create([
        'game_id' => $game->id,
        'date' => today()->toDateString(),
        'views' => 9,
        'downloads' => 2,
    ]);

    $html = Livewire::actingAs($admin)->test(DailyTrafficChart::class)->html();
    $payload = dailyChartPayload($html);
    $decoded = str_replace('\u0022', '"', $html);

    // The chart is drawn in the browser from this payload, so the payload is
    // the part the server can be held to.
    expect($payload['labels'])->toHaveCount(30)
        ->and($payload['labels'][29])->toBe(today()->format('M j'))
        ->and($payload['datasets'])->toHaveCount(2)
        ->and(array_column($payload['datasets'], 'label'))->toBe(['Views', 'Downloads'])
        ->and($payload['datasets'][0]['data'])->toHaveCount(30)
        ->and($payload['datasets'][0]['data'][29])->toBe(9)
        ->and($payload['datasets'][1]['data'][29])->toBe(2)
        ->and($html)->toContain('data-chart-type="line"')
        // Chart.js options, not ApexCharts: the curve lives under elements.line.
        ->and($decoded)->toContain('"elements":{"line":{"tension"');
});

test('the dashboard widgets render for administrators', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(SiteStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Views today')
        ->assertSee('Downloads today')
        ->assertSee('Users');

    Livewire::actingAs($admin)
        ->test(DailyTrafficChart::class)
        ->assertSuccessful()
        ->assertSee('Views and downloads');

    Livewire::actingAs($admin)
        ->test(TopResourcesTable::class)
        ->assertSuccessful()
        ->assertSee('Top resources (7 days)');

    Livewire::actingAs($admin)
        ->test(RecentUsersTable::class)
        ->assertSuccessful()
        ->assertSee('Recent users');
});

test('the stats cards separate today from yesterday', function () {
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->create();

    GameDailyStat::factory()->create([
        'game_id' => $game->id,
        'date' => today()->toDateString(),
        'views' => 12,
        'downloads' => 3,
    ]);
    GameDailyStat::factory()->create([
        'game_id' => $game->id,
        'date' => today()->subDay()->toDateString(),
        'views' => 6,
        'downloads' => 1,
    ]);

    Livewire::actingAs($admin)
        ->test(SiteStatsOverview::class)
        ->assertSuccessful()
        // 12 today against 6 yesterday is +100%, and the week totals both days.
        ->assertSee('vs yesterday (6)')
        ->assertSee('18 in 7 days')
        ->assertSee('4 in 7 days');
});

test('a first day without yesterday traffic reports no change instead of a jump', function () {
    $admin = User::factory()->admin()->create();

    GameDailyStat::factory()->create([
        'date' => today()->toDateString(),
        'views' => 5,
        'downloads' => 2,
    ]);

    Livewire::actingAs($admin)
        ->test(SiteStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Nothing yesterday');
});

test('the top resources table can be re-sorted by a single column', function () {
    $admin = User::factory()->admin()->create();
    $quiet = Game::factory()->create(['slug' => 'sort-quiet-game']);
    $busy = Game::factory()->create(['slug' => 'sort-busy-game']);

    GameDailyStat::factory()->create([
        'game_id' => $quiet->id,
        'date' => today()->toDateString(),
        'views' => 1,
    ]);
    GameDailyStat::factory()->create([
        'game_id' => $busy->id,
        'date' => today()->toDateString(),
        'views' => 50,
    ]);

    // Asking for views ascending has to actually win: if the weighted score
    // stayed in front of it, the busy game would still come first.
    Livewire::actingAs($admin)
        ->test(TopResourcesTable::class)
        ->sortTable('recent_views', 'asc')
        ->assertCanSeeTableRecords([$quiet, $busy], inOrder: true);
});

test('the top resources table lists the games with traffic', function () {
    $admin = User::factory()->admin()->create();
    $busy = Game::factory()->create(['slug' => 'table-busy-game']);
    $quiet = Game::factory()->create(['slug' => 'table-quiet-game']);

    GameDailyStat::factory()->create([
        'game_id' => $busy->id,
        'date' => today()->toDateString(),
        'views' => 25,
        'downloads' => 4,
    ]);

    Livewire::actingAs($admin)
        ->test(TopResourcesTable::class)
        ->assertCanSeeTableRecords([$busy])
        // No traffic in the window means no row, however new the game is.
        ->assertCanNotSeeTableRecords([$quiet]);
});
