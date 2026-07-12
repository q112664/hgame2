<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/resources/{resource}/details', [ResourceController::class, 'details'])
    ->name('resources.details');
Route::get('/resources/{resource}/downloads', [ResourceController::class, 'downloads'])
    ->name('resources.downloads');
Route::get('/resources/{resource}/screenshots', [ResourceController::class, 'screenshots'])
    ->name('resources.screenshots');
Route::get('/resources/{resource}', [ResourceController::class, 'show'])
    ->name('resources.show');

require __DIR__.'/settings.php';
