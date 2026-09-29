<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DailyTrafficChart;
use App\Filament\Widgets\RecentUsersTable;
use App\Filament\Widgets\SiteStatsOverview;
use App\Filament\Widgets\TopResourcesTable;
use App\Models\Category;
use App\Models\Game;
use App\Models\GameDailyStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the filament admin login page is not available', function () {
    $this->get('/admin/login')
        ->assertRedirect('/login');
});

test('guests are redirected to the public login page', function () {
    $this->get('/admin')
        ->assertRedirect(route('login'));
});

test('regular users cannot access the filament admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('administrators land on the operations dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard');
});

test('site stats overview shows daily traffic and users', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $category = Category::factory()->create();
    $game = Game::factory()->create([
        'category_id' => $category->id,
        'views_count' => 10,
        'downloads_count' => 4,
    ]);

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
        ->assertSee('Views today')
        ->assertSee('Downloads today')
        ->assertSee('Users')
        ->assertSee('vs yesterday (6)')
        // 12 + 6 views and 3 + 1 downloads over the week.
        ->assertSee('18 in 7 days')
        ->assertSee('4 in 7 days')
        // The retired cumulative cards must not come back.
        ->assertDontSee('Published resources')
        ->assertDontSee('Engagement');
});

test('dashboard widgets load for administrators', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Dashboard');

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

test('the dashboard is composed of the daily traffic widgets', function () {
    $admin = User::factory()->admin()->create();

    $widgets = Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSuccessful()
        ->instance()
        ->getWidgets();

    // Widgets lazy-load, so the page itself only ships placeholders: the list
    // the Dashboard returns is what actually decides what shows up, in order.
    expect($widgets)->toBe([
        SiteStatsOverview::class,
        DailyTrafficChart::class,
        TopResourcesTable::class,
        RecentUsersTable::class,
    ]);
});

test('administrators can access game management after public login', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/games')
        ->assertOk();
});
