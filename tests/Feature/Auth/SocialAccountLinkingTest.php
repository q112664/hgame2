<?php

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function enableGoogleOAuthForLinking(): void
{
    Setting::setBoolean('oauth_google_enabled', true);
    Setting::set('oauth_google_client_id', 'google-client-id');
    Setting::set('oauth_google_client_secret', 'google-client-secret');
}

function enableDiscordOAuthForLinking(): void
{
    Setting::setBoolean('oauth_discord_enabled', true);
    Setting::set('oauth_discord_client_id', 'discord-client-id');
    Setting::set('oauth_discord_client_secret', 'discord-client-secret');
}

test('security settings include social connections for enabled providers', function () {
    enableGoogleOAuthForLinking();

    $user = User::factory()->create();

    SocialAccount::factory()->google()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'google-linked',
        'email' => 'linked@gmail.com',
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('hasPassword', true)
            ->where('socialConnections', function ($connections) {
                $items = collect($connections)->values()->all();

                expect($items)->toHaveCount(1)
                    ->and($items[0]['provider'])->toBe('google')
                    ->and($items[0]['linked'])->toBeTrue()
                    ->and($items[0]['email'])->toBe('linked@gmail.com')
                    ->and($items[0]['canUnlink'])->toBeTrue();

                return true;
            }),
        );
});

test('social-only users can open security settings without password confirmation', function () {
    enableGoogleOAuthForLinking();

    $user = User::factory()->create([
        'password' => null,
    ]);

    SocialAccount::factory()->google()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'google-only',
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('requiresPasswordConfirmation', false)
            ->where('hasPassword', false)
            ->where('socialConnections.0.canUnlink', false),
        );
});

test('authenticated users can start linking a social account', function () {
    enableGoogleOAuthForLinking();
    Socialite::fake('google');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.social.redirect', ['provider' => 'google']))
        ->assertRedirect();

    expect(session('social_auth_intent'))->toBe('link');
});

test('oauth callback links a social account for the authenticated user', function () {
    enableDiscordOAuthForLinking();

    $user = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    Socialite::fake('discord', SocialiteUser::fake([
        'id' => 'discord-link-1',
        'name' => 'Discord User',
        'email' => 'discord@example.com',
    ]));

    $this->actingAs($user)
        ->withSession(['social_auth_intent' => 'link'])
        ->get(route('auth.social.callback', ['provider' => 'discord']))
        ->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Discord account linked.'),
        ]);

    expect(SocialAccount::query()->where([
        'user_id' => $user->id,
        'provider' => 'discord',
        'provider_user_id' => 'discord-link-1',
    ])->exists())->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

test('oauth callback cannot link a social identity already owned by another user', function () {
    enableGoogleOAuthForLinking();

    $owner = User::factory()->create();
    SocialAccount::factory()->google()->create([
        'user_id' => $owner->id,
        'provider_user_id' => 'google-owned',
    ]);

    $user = User::factory()->create();

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-owned',
        'name' => 'Taken Identity',
        'email' => 'taken@example.com',
    ]));

    $this->actingAs($user)
        ->withSession(['social_auth_intent' => 'link'])
        ->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('social');

    expect(SocialAccount::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('users can unlink a social account when another sign-in method remains', function () {
    enableGoogleOAuthForLinking();

    $user = User::factory()->create();

    SocialAccount::factory()->google()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'google-unlink',
    ]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('security.social.unlink', ['provider' => 'google']))
        ->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('Google account unlinked.'),
        ]);

    expect(SocialAccount::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('users cannot unlink their only sign-in method', function () {
    enableGoogleOAuthForLinking();

    $user = User::factory()->create([
        'password' => null,
    ]);

    SocialAccount::factory()->google()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'google-only',
    ]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('security.social.unlink', ['provider' => 'google']))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('social');

    expect(SocialAccount::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('a disabled provider does not count as a remaining sign-in method', function () {
    enableGoogleOAuthForLinking();

    $user = User::factory()->create([
        'password' => null,
    ]);

    SocialAccount::factory()->google()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'google-linked',
    ]);
    SocialAccount::factory()->discord()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'discord-linked',
    ]);

    // Google login is disabled site-wide, so unlinking Discord would leave
    // the user with no usable sign-in method.
    Setting::setBoolean('oauth_google_enabled', false);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('security.social.unlink', ['provider' => 'discord']))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('social');

    expect(SocialAccount::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('password can be set without a current password for social-only users', function () {
    $user = User::factory()->create([
        'password' => null,
    ]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect($user->refresh()->hasPassword())->toBeTrue();
});
