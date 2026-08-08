<?php

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function enableGoogleOAuth(): void
{
    Setting::setBoolean('oauth_google_enabled', true);
    Setting::set('oauth_google_client_id', 'google-client-id');
    Setting::set('oauth_google_client_secret', 'google-client-secret');
}

function enableDiscordOAuth(): void
{
    Setting::setBoolean('oauth_discord_enabled', true);
    Setting::set('oauth_discord_client_id', 'discord-client-id');
    Setting::set('oauth_discord_client_secret', 'discord-client-secret');
}

test('social providers are shared when configured and enabled', function () {
    enableGoogleOAuth();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('socialProviders', ['google'])
            ->where('authModal.socialProviders', ['google'])
        );
});

test('social providers are not shared when disabled', function () {
    Setting::set('oauth_google_client_id', 'google-client-id');
    Setting::set('oauth_google_client_secret', 'google-client-secret');
    Setting::setBoolean('oauth_google_enabled', false);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('socialProviders', [])
        );
});

test('oauth redirect is not found when the provider is disabled', function () {
    $this->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertNotFound();
});

test('oauth redirect sends the user to the provider when enabled', function () {
    enableGoogleOAuth();
    Socialite::fake('google');

    $this->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect();
});

test('oauth callback creates a new user and logs them in', function () {
    enableGoogleOAuth();

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-100',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'verified_email' => true,
        'avatar' => 'https://example.com/ada.png',
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(SocialAccount::query()->where([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-100',
        ])->exists())->toBeTrue();
});

test('oauth callback logs in an existing linked social account', function () {
    enableDiscordOAuth();

    $user = User::factory()->create([
        'email' => 'linked@example.com',
    ]);

    SocialAccount::factory()->discord()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'discord-55',
        'email' => 'linked@example.com',
    ]);

    Socialite::fake('discord', SocialiteUser::fake([
        'id' => 'discord-55',
        'name' => 'Linked User',
        'email' => 'linked@example.com',
        'verified' => true,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'discord']))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
});

test('oauth callback links a provider to an existing user by email', function () {
    enableGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'email_verified_at' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-200',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'verified_email' => true,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    expect(User::query()->count())->toBe(1)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(SocialAccount::query()->where([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-200',
        ])->exists())->toBeTrue();
});

test('oauth callback fails when the provider does not return an email for a new user', function () {
    enableDiscordOAuth();

    Socialite::fake('discord', SocialiteUser::fake([
        'id' => 'discord-no-email',
        'name' => 'No Email',
        'email' => null,
        'verified' => true,
    ]));

    $this->from(route('login'))
        ->get(route('auth.social.callback', ['provider' => 'discord']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('oauth callback rejects an unverified provider email', function () {
    enableGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'victim@example.com',
        'email_verified_at' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-unverified',
        'name' => 'Attacker',
        'email' => 'victim@example.com',
        'verified_email' => false,
    ]));

    $this->from(route('login'))
        ->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
    expect(User::query()->count())->toBe(1)
        ->and($user->refresh()->email_verified_at)->toBeNull()
        ->and(SocialAccount::query()->count())->toBe(0);
});

test('discord driver requests consent for first-time authorization', function () {
    enableDiscordOAuth();

    $driver = SocialAuth::driver('discord');

    $consent = new ReflectionProperty($driver, 'consent');

    expect($consent->getValue($driver))->toBeTrue();
});

test('oauth callback handles provider errors without crashing', function () {
    enableGoogleOAuth();

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(
            Mockery::mock(AbstractProvider::class)
                ->shouldReceive('scopes')
                ->andReturnSelf()
                ->shouldReceive('user')
                ->andThrow(new Exception('provider is down'))
                ->getMock(),
        );

    $this->from(route('login'))
        ->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
});

test('social login ignores a cross-host redirect parameter', function () {
    enableGoogleOAuth();

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-safe-redirect',
        'name' => 'Safe Redirect',
        'email' => 'safe@example.com',
        'verified_email' => true,
    ]));

    $this->get(route('auth.social.redirect', ['provider' => 'google']).'?redirect=https://evil.example.com/phish')
        ->assertRedirect();

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('home'));
});

test('social login bypasses the two-factor challenge for 2FA users', function () {
    enableGoogleOAuth();

    $user = User::factory()->withTwoFactor()->create([
        'email' => 'twofactor@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-2fa',
        'name' => 'Two Factor User',
        'email' => 'twofactor@example.com',
        'verified_email' => true,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('social auth is only enabled when credentials and toggle are present', function () {
    expect(SocialAuth::isEnabled('google'))->toBeFalse();

    Setting::set('oauth_google_client_id', 'id');
    Setting::set('oauth_google_client_secret', 'secret');
    expect(SocialAuth::isEnabled('google'))->toBeFalse();

    Setting::setBoolean('oauth_google_enabled', true);
    expect(SocialAuth::isEnabled('google'))->toBeTrue();
});
