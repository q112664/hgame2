<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SocialAuth
{
    public const string PROVIDER_GOOGLE = 'google';

    public const string PROVIDER_DISCORD = 'discord';

    public const string PROVIDER_TELEGRAM = 'telegram';

    /**
     * @var list<string>
     */
    public const array PROVIDERS = [
        self::PROVIDER_GOOGLE,
        self::PROVIDER_DISCORD,
        self::PROVIDER_TELEGRAM,
    ];

    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    public static function clientId(string $provider): string
    {
        $fromSettings = Setting::get("oauth_{$provider}_client_id");

        if (filled($fromSettings)) {
            return static::normalizeClientId($provider, (string) $fromSettings);
        }

        if ($provider === self::PROVIDER_TELEGRAM) {
            return static::normalizeClientId(
                $provider,
                (string) config('services.telegram.bot', ''),
            );
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
        $credentials = $provider === self::PROVIDER_TELEGRAM
            ? [
                'bot' => static::clientId($provider),
                'client_id' => null,
                'client_secret' => static::clientSecret($provider),
                'redirect' => static::redirectUri($provider),
            ]
            : [
                'client_id' => static::clientId($provider),
                'client_secret' => static::clientSecret($provider),
                'redirect' => static::redirectUri($provider),
            ];

        config([
            "services.{$provider}" => array_merge(
                (array) config("services.{$provider}", []),
                $credentials,
            ),
        ]);
    }

    public static function redirect(string $provider): RedirectResponse
    {
        if ($provider === self::PROVIDER_TELEGRAM) {
            static::applyProviderConfig($provider);

            return redirect()->away(static::telegramAuthorizationUrl());
        }

        return static::driver($provider)->redirect();
    }

    public static function driver(string $provider): Provider
    {
        static::applyProviderConfig($provider);

        $driver = Socialite::driver($provider);

        // Telegram's driver is not an OAuth scope exchange. Calling scopes()
        // would build a query the login widget does not use.
        if ($provider !== self::PROVIDER_TELEGRAM && $driver instanceof AbstractProvider) {
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

    /**
     * Full-page Telegram Login Widget redirect. The packaged driver returns
     * an HTML page that embeds telegram-widget.js; this URL sends the browser
     * to Telegram and back to the callback with the signed login payload.
     */
    private static function telegramAuthorizationUrl(): string
    {
        $botId = strstr(static::clientSecret(self::PROVIDER_TELEGRAM), ':', true);

        if (! is_string($botId) || $botId === '' || ! ctype_digit($botId)) {
            throw new InvalidArgumentException('Telegram bot token is missing a numeric bot id.');
        }

        $returnTo = static::redirectUri(self::PROVIDER_TELEGRAM);

        return 'https://oauth.telegram.org/auth?'.http_build_query([
            'bot_id' => $botId,
            'origin' => static::originFromUrl($returnTo),
            'return_to' => $returnTo,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function originFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private static function normalizeClientId(string $provider, string $clientId): string
    {
        if ($provider !== self::PROVIDER_TELEGRAM) {
            return $clientId;
        }

        return ltrim(trim($clientId), '@');
    }
}
