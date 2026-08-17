<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings', fn () => to_route('profile.edit'))->name('settings.edit');
    Route::get('settings/profile', [SettingsController::class, 'profile'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:6,1')
        ->name('profile.avatar.update');
    Route::delete('settings/profile/avatar', [ProfileController::class, 'destroyAvatar'])
        ->middleware('throttle:6,1')
        ->name('profile.avatar.destroy');
    Route::get('settings/security', [SettingsController::class, 'security'])->name('security.edit');
    Route::post('settings/security/confirm', [SettingsController::class, 'confirmSecurity'])
        ->middleware('throttle:6,1')
        ->name('security.confirm');
    Route::get('settings/security/social/{provider}/redirect', [SocialAuthController::class, 'linkRedirect'])
        ->name('security.social.redirect')
        ->whereIn('provider', ['google', 'discord'])
        ->middleware('throttle:20,1');
    Route::delete('settings/security/social/{provider}', [SocialAuthController::class, 'unlink'])
        ->name('security.social.unlink')
        ->whereIn('provider', ['google', 'discord'])
        ->middleware('throttle:20,1');
    Route::get('settings/appearance', fn () => to_route('profile.edit'))
        ->name('appearance.edit');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('settings.edit'),
        'manage' => route('settings.edit'),
    ]);
})->name('well-known.passkeys');
