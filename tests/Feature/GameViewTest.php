<?php

use App\Actions\Games\RecordGameView;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function inertiaHeaders(array $extra = []): array
{
    $version = app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '';

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        ...$extra,
    ];
}

test('visiting a resource page records a view on every full page load', function () {
    $game = Game::factory()->create([
        'slug' => 'viewed-game',
        'views_count' => 10,
    ]);

    $this->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.views', 11)
        );

    expect($game->fresh()->views_count)->toBe(11);

    $this->get(route('resources.show', $game->slug))->assertOk();

    expect($game->fresh()->views_count)->toBe(12);
});

test('recording a view does not bump game updated_at for sitemap lastmod', function () {
    $frozen = now()->subDay()->startOfSecond();

    $game = Game::factory()->create([
        'slug' => 'stable-lastmod-game',
        'views_count' => 0,
        'created_at' => $frozen,
        'updated_at' => $frozen,
    ]);

    // Force the timestamp so factory/create side effects cannot skew the check.
    $game->forceFill(['updated_at' => $frozen])->saveQuietly();

    $this->get(route('resources.show', $game->slug))->assertOk();

    $fresh = $game->fresh();

    expect($fresh->views_count)->toBe(1)
        ->and($fresh->updated_at?->equalTo($frozen))->toBeTrue();
});

test('legacy tab urls redirect without recording a view', function () {
    $game = Game::factory()->create([
        'slug' => 'tab-switch-game',
        'views_count' => 3,
    ]);

    $this->get(route('resources.downloads', $game->slug))
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', $game->slug).'#downloads');

    expect($game->fresh()->views_count)->toBe(3);

    $this->get(route('resources.screenshots', $game->slug))
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', $game->slug).'#screenshots');

    expect($game->fresh()->views_count)->toBe(3);
});

test('inertia comment pagination on the same resource does not record a view', function () {
    $game = Game::factory()->create([
        'slug' => 'referer-tab-game',
        'views_count' => 3,
    ]);

    $request = Request::create(
        route('resources.show', ['resource' => $game->slug, 'page' => 2]),
        'GET',
        server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_REFERER' => route('resources.show', $game->slug),
        ],
    );

    app(RecordGameView::class)($request, $game);

    expect($game->fresh()->views_count)->toBe(3);
});

test('inertia navigation from another page still records a view', function () {
    $game = Game::factory()->create([
        'slug' => 'from-index-game',
        'views_count' => 0,
    ]);

    $this->withHeaders(inertiaHeaders())
        ->from(route('resources.index'))
        ->get(route('resources.show', $game->slug))
        ->assertOk();

    expect($game->fresh()->views_count)->toBe(1);
});

test('redirecting from the legacy details route does not record a view', function () {
    $game = Game::factory()->create([
        'slug' => 'redirect-game',
        'views_count' => 5,
    ]);

    $this->get(route('resources.details', $game->slug))
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', $game->slug));

    expect($game->fresh()->views_count)->toBe(5);
});
