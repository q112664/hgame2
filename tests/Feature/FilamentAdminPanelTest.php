<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\LatestResourcesTable;
use App\Filament\Widgets\RecentUsersTable;
use App\Filament\Widgets\SiteStatsOverview;
use App\Models\Category;
use App\Models\Game;
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

test('site stats overview shows operational metrics', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $category = Category::factory()->create();
    Game::factory()->count(3)->create([
        'category_id' => $category->id,
        'views_count' => 10,
        'downloads_count' => 4,
    ]);
    Game::factory()->draft()->create([
        'category_id' => $category->id,
    ]);

    Livewire::actingAs($admin)
        ->test(SiteStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Published resources')
        ->assertSee('Users')
        ->assertSee('Total views')
        ->assertSee('Engagement')
        ->assertSee('3')
        ->assertSee('1 draft');
});

test('dashboard widgets load for administrators', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Dashboard');

    Livewire::actingAs($admin)
        ->test(LatestResourcesTable::class)
        ->assertSuccessful()
        ->assertSee('Latest resources');

    Livewire::actingAs($admin)
        ->test(RecentUsersTable::class)
        ->assertSuccessful()
        ->assertSee('Recent users');
});

test('administrators can access game management after public login', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/games')
        ->assertOk();
});
