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
        ->and($user->avatarPath())->toBe('https://example.com/ada.png')
        ->and($user->avatar)->toBe('https://example.com/ada.png')
        ->and(SocialAccount::query()->where([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-100',
            'avatar' => 'https://example.com/ada.png',
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

function enableTelegramOAuth(): void
{
    Setting::setBoolean('oauth_telegram_enabled', true);
    Setting::set('oauth_telegram_client_id', '@sample_bot');
    Setting::set('oauth_telegram_client_secret', '123456789:test-token');
}

test('telegram login is shared when the bot is configured and enabled', function () {
    enableTelegramOAuth();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('socialProviders', ['telegram'])
            ->where('authModal.socialProviders', ['telegram'])
        );
});

test('telegram redirect is not found when the provider is disabled', function () {
    $this->get(route('auth.social.redirect', ['provider' => 'telegram']))
        ->assertNotFound();
});

test('telegram redirect is not found when the bot token is missing', function () {
    Setting::setBoolean('oauth_telegram_enabled', true);
    Setting::set('oauth_telegram_client_id', 'sample_bot');

    $this->get(route('auth.social.redirect', ['provider' => 'telegram']))
        ->assertNotFound();
});

test('telegram redirect sends the browser to the login widget', function () {
    enableTelegramOAuth();

    $returnTo = route('auth.social.callback', ['provider' => 'telegram'], absolute: true);

    $this->get(route('auth.social.redirect', ['provider' => 'telegram']))
        ->assertRedirect();

    $location = urldecode((string) $this->get(route('auth.social.redirect', ['provider' => 'telegram']))->headers->get('Location'));

    expect($location)
        ->toStartWith('https://oauth.telegram.org/auth?')
        ->toContain('bot_id=123456789')
        ->toContain('return_to='.$returnTo)
        ->not->toContain('telegram-widget.js');
});

test('telegram callback creates a user without an email', function () {
    enableTelegramOAuth();

    Socialite::fake('telegram', SocialiteUser::fake([
        'id' => '9001',
        'name' => 'Ada Lovelace',
        'nickname' => 'ada',
        'email' => 'ada@example.com',
        'avatar' => 'https://cdn.example/ada.jpg',
    ]));

    $existing = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'telegram']))
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();

    $user = auth()->user();

    expect($user)->not->toBeNull()
        ->and($user->is($existing))->toBeFalse()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->avatarPath())->toBe('https://cdn.example/ada.jpg')
        ->and(User::query()->count())->toBe(2)
        ->and(SocialAccount::query()->where([
            'user_id' => $user->id,
            'provider' => 'telegram',
            'provider_user_id' => '9001',
            'email' => null,
            'avatar' => 'https://cdn.example/ada.jpg',
        ])->exists())->toBeTrue();
});

test('telegram callback uses the username when telegram sends no display name', function () {
    enableTelegramOAuth();

    Socialite::fake('telegram', SocialiteUser::fake([
        'id' => '9002',
        'name' => null,
        'nickname' => 'onlyuser',
        'email' => null,
        'avatar' => null,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'telegram']))
        ->assertRedirect(route('home'));

    expect(auth()->user()?->name)->toBe('onlyuser');
});

test('telegram callback logs into the linked user and refreshes the name', function () {
    enableTelegramOAuth();

    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => null,
        'email_verified_at' => null,
        'password' => null,
    ]);

    SocialAccount::factory()->telegram()->create([
        'user_id' => $user->id,
        'provider_user_id' => '9001',
    ]);

    Socialite::fake('telegram', SocialiteUser::fake([
        'id' => '9001',
        'name' => 'New Name',
        'nickname' => 'newname',
        'email' => 'ignored@example.com',
        'avatar' => 'https://cdn.example/new.jpg',
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'telegram']))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    expect(User::query()->count())->toBe(1)
        ->and($user->name)->toBe('New Name')
        ->and($user->email)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->avatarPath())->toBe('https://cdn.example/new.jpg')
        ->and($user->socialAccounts()->first()?->avatar)->toBe('https://cdn.example/new.jpg')
        ->and($user->socialAccounts()->first()?->email)->toBeNull();
});

test('social login copies a provider avatar onto a user who has none', function () {
    enableDiscordOAuth();

    $user = User::factory()->create([
        'email' => 'linked@example.com',
        'avatar' => null,
    ]);

    SocialAccount::factory()->discord()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'discord-55',
        'email' => 'linked@example.com',
        'avatar' => null,
    ]);

    Socialite::fake('discord', SocialiteUser::fake([
        'id' => 'discord-55',
        'name' => 'Linked User',
        'email' => 'linked@example.com',
        'verified' => true,
        'avatar' => 'https://cdn.discordapp.com/avatars/55/new.jpg',
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'discord']))
        ->assertRedirect(route('home'));

    expect($user->refresh()->avatarPath())->toBe('https://cdn.discordapp.com/avatars/55/new.jpg');
});

test('social login refreshes a previously synced provider avatar', function () {
    enableGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/old.png',
    ]);

    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-100',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/old.png',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-100',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'verified_email' => true,
        'avatar' => 'https://example.com/new.png',
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('home'));

    expect($user->refresh()->avatarPath())->toBe('https://example.com/new.png');
});

test('social login keeps an uploaded avatar', function () {
    enableGoogleOAuth();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'avatar' => 'avatars/custom.webp',
    ]);

    SocialAccount::factory()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-100',
        'email' => 'ada@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-100',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'verified_email' => true,
        'avatar' => 'https://example.com/provider.png',
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('home'));

    expect($user->refresh()->avatarPath())->toBe('avatars/custom.webp')
        ->and($user->socialAccounts()->first()?->avatar)->toBe('https://example.com/provider.png');
});

test('a missing provider avatar does not clear the user avatar', function () {
    enableDiscordOAuth();

    $user = User::factory()->create([
        'email' => 'linked@example.com',
        'avatar' => 'https://cdn.discordapp.com/avatars/55/kept.jpg',
    ]);

    SocialAccount::factory()->discord()->create([
        'user_id' => $user->id,
        'provider_user_id' => 'discord-55',
        'email' => 'linked@example.com',
        'avatar' => 'https://cdn.discordapp.com/avatars/55/kept.jpg',
    ]);

    Socialite::fake('discord', SocialiteUser::fake([
        'id' => 'discord-55',
        'name' => 'Linked User',
        'email' => 'linked@example.com',
        'verified' => true,
        'avatar' => null,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'discord']))
        ->assertRedirect(route('home'));

    expect($user->refresh()->avatarPath())->toBe('https://cdn.discordapp.com/avatars/55/kept.jpg');
});

test('two telegram users can both exist without an email', function () {
    enableTelegramOAuth();

    Socialite::fake('telegram', SocialiteUser::fake([
        'id' => '9101',
        'name' => 'First',
        'email' => null,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'telegram']))
        ->assertRedirect(route('home'));

    $this->post(route('logout'));

    Socialite::fake('telegram', SocialiteUser::fake([
        'id' => '9102',
        'name' => 'Second',
        'email' => null,
    ]));

    $this->get(route('auth.social.callback', ['provider' => 'telegram']))
        ->assertRedirect(route('home'));

    expect(User::query()->whereNull('email')->count())->toBe(2);
});

test('telegram callback handles driver errors without creating a user', function () {
    enableTelegramOAuth();

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('user')->once()->andThrow(new Exception('provider is down'));
    $driver->shouldNotReceive('scopes');

    Socialite::shouldReceive('driver')
        ->once()
        ->with('telegram')
        ->andReturn($driver);

    $this->from(route('login'))
        ->get(route('auth.social.callback', ['provider' => 'telegram']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('telegram credentials map the bot username and keep the oauth client id empty', function () {
    config([
        'services.telegram.bot' => '@env_bot',
        'services.telegram.client_id' => 'must-stay-unused',
        'services.telegram.client_secret' => '42:env-token',
    ]);

    expect(SocialAuth::clientId('telegram'))->toBe('env_bot')
        ->and(SocialAuth::isConfigured('telegram'))->toBeTrue();

    Setting::set('oauth_telegram_client_id', '@SampleBot');
    Setting::set('oauth_telegram_client_secret', '99:saved-token');

    SocialAuth::applyProviderConfig('telegram');

    expect(config('services.telegram.bot'))->toBe('SampleBot')
        ->and(config('services.telegram.client_id'))->toBeNull()
        ->and(config('services.telegram.client_secret'))->toBe('99:saved-token');
});
