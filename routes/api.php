<?php

use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\GameDetailVersionController;
use App\Http\Controllers\Api\V1\GameReleaseController;
use App\Http\Controllers\Api\V1\GameScreenshotController;
use App\Http\Controllers\Api\V1\GameTagController;
use App\Http\Controllers\Api\V1\ResourceSourceController;
use App\Http\Controllers\Api\V1\TaxonomyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'admin', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('taxonomies', [TaxonomyController::class, 'index'])
            ->name('api.v1.taxonomies');

        Route::get('sources', [ResourceSourceController::class, 'index'])
            ->name('api.v1.sources.index');

        Route::post('sources', [ResourceSourceController::class, 'store'])
            ->name('api.v1.sources.store');

        Route::delete('sources/{source:slug}', [ResourceSourceController::class, 'destroy'])
            ->name('api.v1.sources.destroy');

        Route::get('games', [GameController::class, 'index'])
            ->name('api.v1.games.index');

        Route::post('games', [GameController::class, 'store'])
            ->name('api.v1.games.store');

        Route::get('games/{game:slug}', [GameController::class, 'show'])
            ->name('api.v1.games.show');

        Route::match(['put', 'patch'], 'games/{game:slug}', [GameController::class, 'update'])
            ->name('api.v1.games.update');

        Route::delete('games/{game:slug}', [GameController::class, 'destroy'])
            ->name('api.v1.games.destroy');

        Route::scopeBindings()->group(function (): void {
            Route::post('games/{game:slug}/screenshots', [GameScreenshotController::class, 'store'])
                ->name('api.v1.games.screenshots.store');

            Route::match(['put', 'patch'], 'games/{game:slug}/screenshots/{screenshot}', [GameScreenshotController::class, 'update'])
                ->name('api.v1.games.screenshots.update');

            Route::delete('games/{game:slug}/screenshots/{screenshot}', [GameScreenshotController::class, 'destroy'])
                ->name('api.v1.games.screenshots.destroy');

            Route::post('games/{game:slug}/releases', [GameReleaseController::class, 'store'])
                ->name('api.v1.games.releases.store');

            Route::match(['put', 'patch'], 'games/{game:slug}/releases/{release}', [GameReleaseController::class, 'update'])
                ->name('api.v1.games.releases.update');

            Route::delete('games/{game:slug}/releases/{release}', [GameReleaseController::class, 'destroy'])
                ->name('api.v1.games.releases.destroy');
        });

        Route::post('games/{game:slug}/tags', [GameTagController::class, 'store'])
            ->name('api.v1.games.tags.store');

        Route::delete('games/{game:slug}/tags/{tag}', [GameTagController::class, 'destroy'])
            ->name('api.v1.games.tags.destroy');

        Route::match(['put', 'patch'], 'games/{game:slug}/detail-versions/{language}', [GameDetailVersionController::class, 'upsert'])
            ->name('api.v1.games.detail-versions.upsert');

        Route::delete('games/{game:slug}/detail-versions/{language}', [GameDetailVersionController::class, 'destroy'])
            ->name('api.v1.games.detail-versions.destroy');
    });
