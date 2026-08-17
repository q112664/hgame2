<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('settings page includes security settings without a password confirmation gate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('activeTab', 'security')
            ->where('requiresPasswordConfirmation', false)
            ->where('canManagePasskeys', false)
            ->where('passkeys', [])
            ->where('hasPassword', true)
            ->where('socialConnections', [])
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('security password can be confirmed inline', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->post(route('security.confirm'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(session('auth.password_confirmed_at'))->toBeInt();
});

test('security password confirmation rejects an incorrect password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->post(route('security.confirm'), ['password' => 'incorrect-password'])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('security.edit'));
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Password updated.'),
        ]);

    expect(Hash::check('new-password1', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
