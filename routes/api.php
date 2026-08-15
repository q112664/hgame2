<?php

use App\Http\Controllers\Api\V1\GameController;
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
    });
