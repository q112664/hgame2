<?php

use App\Http\Controllers\DocController;
use App\Http\Controllers\DownloadLinkContinueController;
use App\Http\Controllers\DownloadLinkController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameCommentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');

// Legacy Filament login URL — admin signs in on the public site only.
Route::redirect('/admin/login', '/login');

Route::get('/docs', [DocController::class, 'index'])->name('docs.index');
Route::get('/docs/{doc}', [DocController::class, 'show'])->name('docs.show');

Route::get('/go/{downloadLink}', [DownloadLinkController::class, 'show'])
    ->name('download-links.show')
    ->whereNumber('downloadLink');
Route::post('/go/{downloadLink}', DownloadLinkContinueController::class)
    ->name('download-links.continue')
    ->whereNumber('downloadLink')
    ->middleware('throttle:20,1');

Route::get('/resources', [ResourceController::class, 'index'])
    ->name('resources.index');
Route::get('/resources/random', [ResourceController::class, 'random'])
    ->name('resources.random');
Route::get('/resources/{resource}/details', [ResourceController::class, 'details'])
    ->name('resources.details');
Route::get('/resources/{resource}/downloads', [ResourceController::class, 'downloads'])
    ->name('resources.downloads');
Route::get('/resources/{resource}/screenshots', [ResourceController::class, 'screenshots'])
    ->name('resources.screenshots');
Route::get('/resources/{resource}/comments', [ResourceController::class, 'comments'])
    ->name('resources.comments');
Route::get('/resources/{resource}', [ResourceController::class, 'show'])
    ->name('resources.show');

Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/resources/{resource}/favorite', [FavoriteController::class, 'toggle'])
        ->name('resources.favorite');
    Route::delete('/resources/{resource}/favorite', [FavoriteController::class, 'destroy'])
        ->name('resources.favorite.destroy');
    Route::post('/resources/{resource}/downloads/seen', [ResourceController::class, 'markDownloadsSeen'])
        ->name('resources.downloads.seen');
    Route::post('/resources/{resource}/comments', [GameCommentController::class, 'store'])
        ->name('resources.comments.store')
        ->middleware('throttle:20,1');
    Route::patch('/resources/{resource}/comments/{comment}', [GameCommentController::class, 'update'])
        ->name('resources.comments.update')
        ->whereNumber('comment')
        ->scopeBindings()
        ->middleware('throttle:30,1');
    Route::delete('/resources/{resource}/comments/{comment}', [GameCommentController::class, 'destroy'])
        ->name('resources.comments.destroy')
        ->whereNumber('comment')
        ->scopeBindings();

    Route::get('/notifications/{tab?}', [NotificationController::class, 'index'])
        ->name('notifications.index')
        ->whereIn('tab', ['all', 'comments', 'favorites', 'system']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/clear', [NotificationController::class, 'clear'])
        ->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read')
        ->whereUuid('notification');
});

require __DIR__.'/settings.php';
