<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('profile settings page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('activeTab', 'profile')
            ->where('mustVerifyEmail', false)
            ->where('requiresPasswordConfirmation', true)
            ->missing('canManagePasskeys')
            ->missing('canManageTwoFactor')
            ->missing('passwordRules'),
        );
});

test('settings root redirects to the profile settings route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertRedirect(route('profile.edit'));
});

test('appearance settings page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('activeTab', 'appearance')
            ->where('requiresPasswordConfirmation', true)
            ->missing('passwordRules')
            ->missing('canManagePasskeys')
            ->missing('canManageTwoFactor'),
        );
});

test('settings tabs include security props when password is confirmed', function (string $routeName, string $activeTab) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($routeName))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('activeTab', $activeTab)
            ->where('requiresPasswordConfirmation', false)
            ->has('passwordRules')
            ->has('canManagePasskeys')
            ->has('canManageTwoFactor')
            ->has('passkeys'),
        );
})->with([
    'profile' => ['profile.edit', 'profile'],
    'appearance' => ['appearance.edit', 'appearance'],
]);
