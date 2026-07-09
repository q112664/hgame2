<?php

use App\Http\Controllers\ResourceController;
use App\Support\MockResources;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'resources' => MockResources::cards(),
    ]);
})->name('home');

Route::get('/resources/{resource}', [ResourceController::class, 'show'])
    ->name('resources.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
