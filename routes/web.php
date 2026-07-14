<?php

use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');

Route::get('/resources', [ResourceController::class, 'index'])
    ->name('resources.index');
Route::get('/resources/{resource}/details', [ResourceController::class, 'details'])
    ->name('resources.details');
Route::get('/resources/{resource}/downloads', [ResourceController::class, 'downloads'])
    ->name('resources.downloads');
Route::get('/resources/{resource}/screenshots', [ResourceController::class, 'screenshots'])
    ->name('resources.screenshots');
Route::get('/resources/{resource}', [ResourceController::class, 'show'])
    ->name('resources.show');

Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/resources/{resource}/favorite', [FavoriteController::class, 'toggle'])
        ->name('resources.favorite');
});

require __DIR__.'/settings.php';
