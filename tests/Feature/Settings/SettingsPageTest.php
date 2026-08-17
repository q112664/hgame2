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
            ->where('requiresPasswordConfirmation', false)
            ->has('canManagePasskeys')
            ->has('canManageTwoFactor')
            ->has('passwordRules'),
        );
});

test('settings root redirects to the profile settings route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertRedirect(route('profile.edit'));
});

test('appearance settings redirect to the profile tab', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertRedirect(route('profile.edit'));
});

test('settings tabs include security props without a password confirmation gate', function (string $routeName, string $activeTab) {
    $user = User::factory()->create();

    $this->actingAs($user)
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
    'security' => ['security.edit', 'security'],
]);
