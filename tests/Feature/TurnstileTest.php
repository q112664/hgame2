<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::set('turnstile_site_key', 'test-site-key');
    Setting::set('turnstile_secret_key', 'test-secret-key');
});

function fakeTurnstileSuccess(): void
{
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);
}

function fakeTurnstileFailure(): void
{
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false]),
    ]);
}

test('turnstile config is shared with the frontend', function () {
    Setting::setBoolean('turnstile_login_enabled', true);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('turnstile.siteKey', 'test-site-key')
            ->where('turnstile.login', true)
            ->where('turnstile.register', false)
            ->where('turnstile.forgotPassword', false)
            ->where('turnstile.download', false)
            ->where('authModal.turnstile.login', true)
        );
});

test('login does not require turnstile when the feature is disabled', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();
});

test('login requires a valid turnstile token when enabled', function () {
    Setting::setBoolean('turnstile_login_enabled', true);
    $user = User::factory()->create();

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(Turnstile::FIELD);

    $this->assertGuest();
});

test('login succeeds with a verified turnstile token when enabled', function () {
    Setting::setBoolean('turnstile_login_enabled', true);
    fakeTurnstileSuccess();
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        Turnstile::FIELD => 'valid-token',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    Http::assertSentCount(1);
});

test('registration requires turnstile when enabled', function () {
    Setting::setBoolean('turnstile_register_enabled', true);

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(Turnstile::FIELD);

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

test('registration succeeds with a verified turnstile token when enabled', function () {
    Setting::setBoolean('turnstile_register_enabled', true);
    fakeTurnstileSuccess();

    $this->post(route('register.store'), [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        Turnstile::FIELD => 'valid-token',
    ])->assertRedirect();

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeTrue();
});

test('forgot password requires turnstile when enabled', function () {
    Setting::setBoolean('turnstile_forgot_password_enabled', true);
    $user = User::factory()->create();

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => $user->email,
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors(Turnstile::FIELD);
});

test('forgot password accepts a verified turnstile token when enabled', function () {
    Setting::setBoolean('turnstile_forgot_password_enabled', true);
    fakeTurnstileSuccess();
    $user = User::factory()->create();

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => $user->email,
            Turnstile::FIELD => 'valid-token',
        ])
        ->assertRedirect();

    Http::assertSentCount(1);
});

test('turnstile feature flags are ignored when keys are missing', function () {
    Setting::set('turnstile_site_key', null);
    Setting::set('turnstile_secret_key', null);
    Setting::setBoolean('turnstile_login_enabled', true);

    expect(Turnstile::isEnabled(Turnstile::FEATURE_LOGIN))->toBeFalse();

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();
});

test('failed turnstile verification rejects the request', function () {
    Setting::setBoolean('turnstile_login_enabled', true);
    fakeTurnstileFailure();
    $user = User::factory()->create();

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            Turnstile::FIELD => 'bad-token',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(Turnstile::FIELD);

    $this->assertGuest();
});
