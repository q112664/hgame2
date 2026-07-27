<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Turnstile
{
    public const string FIELD = 'cf-turnstile-response';

    public const string FEATURE_LOGIN = 'login';

    public const string FEATURE_REGISTER = 'register';

    public const string FEATURE_FORGOT_PASSWORD = 'forgot_password';

    public const string FEATURE_DOWNLOAD = 'download';

    public static function siteKey(): string
    {
        $fromSettings = Setting::get('turnstile_site_key');

        if (filled($fromSettings)) {
            return (string) $fromSettings;
        }

        return (string) config('services.turnstile.site_key', '');
    }

    public static function secretKey(): string
    {
        $fromSettings = Setting::get('turnstile_secret_key');

        if (filled($fromSettings)) {
            return (string) $fromSettings;
        }

        return (string) config('services.turnstile.secret_key', '');
    }

    public static function isConfigured(): bool
    {
        return filled(static::siteKey()) && filled(static::secretKey());
    }

    public static function featureEnabled(string $feature): bool
    {
        return match ($feature) {
            self::FEATURE_LOGIN => Setting::boolean('turnstile_login_enabled', false),
            self::FEATURE_REGISTER => Setting::boolean('turnstile_register_enabled', false),
            self::FEATURE_FORGOT_PASSWORD => Setting::boolean('turnstile_forgot_password_enabled', false),
            self::FEATURE_DOWNLOAD => Setting::boolean('turnstile_download_enabled', false),
            default => false,
        };
    }

    public static function isEnabled(string $feature): bool
    {
        return static::isConfigured() && static::featureEnabled($feature);
    }

    /**
     * Public config for the frontend widget and feature gates.
     *
     * @return array{
     *     siteKey: string|null,
     *     login: bool,
     *     register: bool,
     *     forgotPassword: bool,
     *     download: bool
     * }
     */
    public static function frontendConfig(): array
    {
        $configured = static::isConfigured();

        return [
            'siteKey' => $configured ? static::siteKey() : null,
            'login' => $configured && static::featureEnabled(self::FEATURE_LOGIN),
            'register' => $configured && static::featureEnabled(self::FEATURE_REGISTER),
            'forgotPassword' => $configured && static::featureEnabled(self::FEATURE_FORGOT_PASSWORD),
            'download' => $configured && static::featureEnabled(self::FEATURE_DOWNLOAD),
        ];
    }

    /**
     * Validation rules for a protected form. Empty when the feature is off.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(string $feature): array
    {
        if (! static::isEnabled($feature)) {
            return [];
        }

        return [
            self::FIELD => ['required', 'string'],
        ];
    }

    /**
     * Validate the Turnstile token on the current request for a feature.
     *
     * @throws ValidationException
     */
    public static function validateRequest(string $feature, ?string $token = null): void
    {
        if (! static::isEnabled($feature)) {
            return;
        }

        $token ??= (string) request()->input(self::FIELD, '');

        if ($token === '' || ! static::verify($token)) {
            throw ValidationException::withMessages([
                self::FIELD => [__('Human verification failed. Please try again.')],
            ]);
        }
    }

    public static function verify(string $token, ?string $ip = null): bool
    {
        if ($token === '' || ! static::isConfigured()) {
            return false;
        }

        $response = Http::asForm()
            ->timeout(5)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => static::secretKey(),
                'response' => $token,
                'remoteip' => $ip ?? request()->ip(),
            ]);

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success');
    }
}
