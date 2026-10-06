<?php

use App\Actions\Stats\ListResourceRankings;
use App\Models\Game;
use App\Models\GameDailyStat;
use App\Models\Setting;
use App\RankingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('rankings add views and downloads with equal weight', function () {
    $downloads = rankedGame('download-lead', views: 0, downloads: 4);
    $views = rankedGame('view-lead', views: 3, downloads: 0);

    $slugs = collect(app(ListResourceRankings::class)(RankingPeriod::Day))->pluck('slug')->all();

    expect($slugs)->toBe([$downloads->slug, $views->slug]);
});

test('each ranking window ignores traffic outside its dates', function () {
    $today = rankedGame('today-hit', views: 2, downloads: 1);
    $weekEdge = rankedGame('week-edge', views: 5, downloads: 0, daysAgo: 6);
    $weekOut = rankedGame('week-out', views: 40, downloads: 40, daysAgo: 7);
    $monthEdge = rankedGame('month-edge', views: 6, downloads: 0, daysAgo: 29);
    $monthOut = rankedGame('month-out', views: 80, downloads: 80, daysAgo: 30);

    $day = rankingSlugs(RankingPeriod::Day);
    $week = rankingSlugs(RankingPeriod::Week);
    $month = rankingSlugs(RankingPeriod::Month);

    expect($day)->toBe([$today->slug])
        ->and($week)->toContain($today->slug, $weekEdge->slug)
        ->and($week)->not->toContain($weekOut->slug, $monthEdge->slug, $monthOut->slug)
        ->and($month)->toContain($today->slug, $weekEdge->slug, $weekOut->slug, $monthEdge->slug)
        ->and($month)->not->toContain($monthOut->slug);
});

test('drafts stay off the public rankings', function () {
    $draft = Game::factory()->draft()->create(['slug' => 'draft-hit']);
    GameDailyStat::factory()->for($draft)->create([
        'date' => today()->toDateString(),
        'views' => 500,
        'downloads' => 500,
    ]);
    $published = rankedGame('published-hit', views: 1, downloads: 0);

    expect(rankingSlugs(RankingPeriod::Day))->toBe([$published->slug]);
});

test('equal scores rank the game with more downloads first', function () {
    $moreViews = rankedGame('more-views', views: 10, downloads: 2);
    $moreDownloads = rankedGame('more-downloads', views: 8, downloads: 4);

    expect(rankingSlugs(RankingPeriod::Day))->toBe([
        $moreDownloads->slug,
        $moreViews->slug,
    ]);
});

test('rankings keep only the top 30 games', function () {
    foreach (range(1, 31) as $views) {
        rankedGame('rank-'.$views, views: $views, downloads: 0);
    }

    $entries = app(ListResourceRankings::class)(RankingPeriod::Day);

    expect($entries)->toHaveCount(ListResourceRankings::Limit)
        ->and($entries[0]['slug'])->toBe('rank-31')
        ->and($entries[0]['rank'])->toBe(1)
        ->and($entries[0]['views'])->toBe(31)
        ->and(collect($entries)->pluck('slug')->all())->not->toContain('rank-1');
});

test('ranking pages render one period and do not record a view', function () {
    $today = rankedGame('page-today', views: 1, downloads: 2);
    $earlier = rankedGame('page-earlier', views: 9, downloads: 0, daysAgo: 3);
    $views = $today->views_count;
    $stats = GameDailyStat::query()->count();

    $this->get(route('rankings.day'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rankings/index')
            ->where('period', 'day')
            ->has('entries', 1)
            ->where('entries.0.slug', $today->slug)
            ->where('entries.0.views', 1)
            ->where('entries.0.downloads', 2)
            ->where('entries.0.rank', 1)
            ->where('pageSeo.title', 'Rankings')
            ->where('pageSeo.robots', 'index,follow')
            ->where('pageSeo.canonical', route('rankings.day'))
        );

    $this->get(route('rankings.week'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rankings/index')
            ->where('period', 'week')
            ->has('entries', 2)
            ->where('entries.0.slug', $earlier->slug)
            ->where('pageSeo.title', 'Rankings · 7 days')
            ->where('pageSeo.canonical', route('rankings.week'))
        );

    $this->get(route('rankings.month'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('period', 'month')
            ->where('pageSeo.title', 'Rankings · 30 days')
            ->where('pageSeo.canonical', route('rankings.month'))
            ->has('entries', 2)
        );

    expect($today->fresh()->views_count)->toBe($views)
        ->and(GameDailyStat::query()->count())->toBe($stats);
});

test('a cached ranking stays in place until the cache is cleared', function () {
    $first = rankedGame('cached-first', views: 2, downloads: 0);

    $this->get(route('rankings.day'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 1)
            ->where('entries.0.slug', $first->slug)
        );

    rankedGame('cached-second', views: 20, downloads: 0);

    $this->get(route('rankings.day'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 1)
            ->where('entries.0.slug', $first->slug)
        );

    Cache::flush();

    $this->get(route('rankings.day'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 2)
            ->where('entries.0.slug', 'cached-second')
        );
});

test('sitemap lists the three ranking pages', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect($xml)
        ->toContain(route('rankings.day'))
        ->toContain(route('rankings.week'))
        ->toContain(route('rankings.month'));
});

test('the default menu and a saved menu both expose rankings', function () {
    $default = Setting::defaultNavigationMenu();
    $defaultUrls = array_column($default, 'url');

    expect($defaultUrls)->toContain('/rankings')
        ->and(array_search('/rankings', $defaultUrls, true))
        ->toBe(array_search('/games', $defaultUrls, true) + 1);

    Setting::setNavigationMenu([
        [
            'label' => 'Catalog',
            'url' => '/games',
            'icon' => 'Library',
            'open_in_new_tab' => false,
            'match' => 'prefix',
        ],
        [
            'label' => 'Docs',
            'url' => '/docs',
            'icon' => 'BookOpen',
            'open_in_new_tab' => false,
            'match' => 'prefix',
        ],
    ]);

    $menu = Setting::navigationMenu();

    expect($menu[0]['label'])->toBe('Catalog')
        ->and($menu[1]['label'])->toBe('Rankings')
        ->and($menu[1]['url'])->toBe('/rankings')
        ->and($menu[1]['icon'])->toBe('Flame')
        ->and($menu[2]['label'])->toBe('Docs');
});

function rankedGame(string $slug, int $views, int $downloads, int $daysAgo = 0): Game
{
    $game = Game::factory()->create([
        'slug' => $slug,
        'title' => $slug,
    ]);

    GameDailyStat::factory()->for($game)->create([
        'date' => today()->subDays($daysAgo)->toDateString(),
        'views' => $views,
        'downloads' => $downloads,
    ]);

    return $game;
}

/**
 * @return list<string>
 */
function rankingSlugs(RankingPeriod $period): array
{
    return array_column(app(ListResourceRankings::class)($period), 'slug');
}
