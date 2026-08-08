<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SocialAuth
{
    public const string PROVIDER_GOOGLE = 'google';

    public const string PROVIDER_DISCORD = 'discord';

    /**
     * @var list<string>
     */
    public const array PROVIDERS = [
        self::PROVIDER_GOOGLE,
        self::PROVIDER_DISCORD,
    ];

    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    public static function clientId(string $provider): string
    {
        $fromSettings = Setting::get("oauth_{$provider}_client_id");

        if (filled($fromSettings)) {
            return (string) $fromSettings;
        }

        return (string) config("services.{$provider}.client_id", '');
    }

    public static function clientSecret(string $provider): string
    {
        $fromSettings = Setting::get("oauth_{$provider}_client_secret");

        if (filled($fromSettings)) {
            return (string) $fromSettings;
        }

        return (string) config("services.{$provider}.client_secret", '');
    }

    public static function redirectUri(string $provider): string
    {
        if (Route::has('auth.social.callback')) {
            return route('auth.social.callback', ['provider' => $provider], absolute: true);
        }

        return url("/auth/{$provider}/callback");
    }

    public static function isConfigured(string $provider): bool
    {
        return static::isSupported($provider)
            && filled(static::clientId($provider))
            && filled(static::clientSecret($provider));
    }

    public static function featureEnabled(string $provider): bool
    {
        if (! static::isSupported($provider)) {
            return false;
        }

        return Setting::boolean("oauth_{$provider}_enabled", false);
    }

    public static function isEnabled(string $provider): bool
    {
        return static::isConfigured($provider) && static::featureEnabled($provider);
    }

    /**
     * Enabled provider keys for the frontend.
     *
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider): bool => static::isEnabled($provider),
        ));
    }

    public static function applyProviderConfig(string $provider): void
    {
        config([
            "services.{$provider}" => array_merge(
                (array) config("services.{$provider}", []),
                [
                    'client_id' => static::clientId($provider),
                    'client_secret' => static::clientSecret($provider),
                    'redirect' => static::redirectUri($provider),
                ],
            ),
        ]);
    }

    public static function driver(string $provider): Provider
    {
        static::applyProviderConfig($provider);

        $driver = Socialite::driver($provider);

        if ($driver instanceof AbstractProvider) {
            $driver->scopes(static::scopes($provider));
        }

        // Discord defaults to prompt=none, which fails with consent_required
        // when the user has never authorized the application before.
        if ($provider === self::PROVIDER_DISCORD && $driver instanceof \SocialiteProviders\Discord\Provider) {
            $driver->withConsent();
        }

        return $driver;
    }

    /**
     * @return list<string>
     */
    public static function scopes(string $provider): array
    {
        return match ($provider) {
            self::PROVIDER_GOOGLE => ['openid', 'profile', 'email'],
            self::PROVIDER_DISCORD => ['identify', 'email'],
            default => [],
        };
    }
}
