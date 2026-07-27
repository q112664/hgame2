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

    $this->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.views', 11)
        );

    expect($game->fresh()->views_count)->toBe(11);

    $this->get(route('resources.details', $game->slug))->assertOk();

    expect($game->fresh()->views_count)->toBe(12);
});

test('inertia tab switches within the same resource do not record a view', function () {
    $game = Game::factory()->create([
        'slug' => 'tab-switch-game',
        'views_count' => 3,
    ]);

    $this->withHeaders(inertiaHeaders([
        RecordGameView::TabNavigationHeader => '1',
    ]))
        ->from(route('resources.details', $game->slug))
        ->get(route('resources.downloads', $game->slug))
        ->assertOk();

    expect($game->fresh()->views_count)->toBe(3);

    $this->withHeaders(inertiaHeaders([
        RecordGameView::TabNavigationHeader => '1',
    ]))
        ->from(route('resources.downloads', $game->slug))
        ->get(route('resources.screenshots', $game->slug))
        ->assertOk();

    expect($game->fresh()->views_count)->toBe(3);
});

test('inertia tab switches still skip counting when only the referer fallback is present', function () {
    $game = Game::factory()->create([
        'slug' => 'referer-tab-game',
        'views_count' => 3,
    ]);

    $request = Request::create(
        route('resources.downloads', $game->slug),
        'GET',
        server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_REFERER' => route('resources.details', $game->slug),
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
        ->get(route('resources.details', $game->slug))
        ->assertOk();

    expect($game->fresh()->views_count)->toBe(1);
});

test('redirecting from the resource show route does not record a view', function () {
    $game = Game::factory()->create([
        'slug' => 'redirect-game',
        'views_count' => 5,
    ]);

    $this->get(route('resources.show', $game->slug))
        ->assertRedirect(route('resources.details', $game->slug));

    expect($game->fresh()->views_count)->toBe(5);
});
