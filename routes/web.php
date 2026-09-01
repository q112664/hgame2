<?php

use App\Http\Controllers\DocController;
use App\Http\Controllers\DownloadLinkContinueController;
use App\Http\Controllers\DownloadLinkController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameCommentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');

// Legacy Filament login URL — admin signs in on the public site only.
Route::redirect('/admin/login', '/login');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('auth.social.redirect')
    ->whereIn('provider', ['google', 'discord'])
    ->middleware(['guest', 'throttle:20,1']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('auth.social.callback')
    ->whereIn('provider', ['google', 'discord'])
    ->middleware('throttle:20,1');

Route::get('/docs', [DocController::class, 'index'])->name('docs.index');
Route::get('/docs/{doc}', [DocController::class, 'show'])->name('docs.show');

Route::get('/users/{user}/favorites', [UserProfileController::class, 'favorites'])
    ->name('users.favorites')
    ->where('user', '[A-Za-z0-9]+');
Route::get('/users/{user}', [UserProfileController::class, 'show'])
    ->name('users.show')
    ->where('user', '[A-Za-z0-9]+');

Route::get('/go/{downloadLink}', [DownloadLinkController::class, 'show'])
    ->name('download-links.show')
    ->whereNumber('downloadLink');
Route::post('/go/{downloadLink}', DownloadLinkContinueController::class)
    ->name('download-links.continue')
    ->whereNumber('downloadLink')
    ->middleware('throttle:20,1');

Route::get('/games', [ResourceController::class, 'index'])
    ->name('resources.index');
Route::get('/games/random', [ResourceController::class, 'random'])
    ->name('resources.random');

// Indexable single-dimension taxonomy landings (before {resource} catch-all).
Route::get('/games/tags', [ResourceController::class, 'tagsIndex'])
    ->name('resources.tags');
Route::get('/games/genre/{category:slug}', [ResourceController::class, 'genre'])
    ->name('resources.genre');
Route::get('/games/platform/{platform:slug}', [ResourceController::class, 'platform'])
    ->name('resources.platform');
Route::get('/games/language/{language:code}', [ResourceController::class, 'language'])
    ->name('resources.language');
Route::get('/games/tag/{tag:slug}', [ResourceController::class, 'tag'])
    ->name('resources.tag');

Route::get('/games/{resource}/details', [ResourceController::class, 'details'])
    ->name('resources.details');
Route::get('/games/{resource}/downloads', [ResourceController::class, 'downloads'])
    ->name('resources.downloads');
Route::get('/games/{resource}/screenshots', [ResourceController::class, 'screenshots'])
    ->name('resources.screenshots');
Route::get('/games/{resource}/comments', [ResourceController::class, 'comments'])
    ->name('resources.comments');
Route::get('/games/{resource}', [ResourceController::class, 'show'])
    ->name('resources.show');

Route::middleware('comments.enabled')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::post('/games/{resource}/comments', [GameCommentController::class, 'store'])
            ->name('resources.comments.store')
            ->middleware('throttle:20,1');
        Route::patch('/games/{resource}/comments/{comment}', [GameCommentController::class, 'update'])
            ->name('resources.comments.update')
            ->whereNumber('comment')
            ->scopeBindings()
            ->middleware('throttle:30,1');
        Route::delete('/games/{resource}/comments/{comment}', [GameCommentController::class, 'destroy'])
            ->name('resources.comments.destroy')
            ->whereNumber('comment')
            ->scopeBindings();
    });
});

Route::get('/resources/{path?}', [ResourceController::class, 'legacy'])
    ->where('path', '.*');

Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/games/{resource}/favorite', [FavoriteController::class, 'toggle'])
        ->name('resources.favorite');
    Route::delete('/games/{resource}/favorite', [FavoriteController::class, 'destroy'])
        ->name('resources.favorite.destroy');
    Route::post('/games/{resource}/like', [LikeController::class, 'toggle'])
        ->name('resources.like');
    Route::post('/games/{resource}/downloads/seen', [ResourceController::class, 'markDownloadsSeen'])
        ->name('resources.downloads.seen');

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
