<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveSocialUser
{
    public function __construct(private readonly StoreSocialAvatar $socialAvatars) {}

    public function handle(string $provider, SocialiteUser $socialUser): User
    {
        $providerUserId = (string) $socialUser->getId();

        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'social' => [__('Unable to complete social login. Please try again.')],
            ]);
        }

        $email = $this->normalizeEmail($socialUser->getEmail());
        $name = $this->resolveName($socialUser);
        $avatar = $this->normalizeNullableString($socialUser->getAvatar());
        $emailVerified = $this->isEmailVerified($provider, $socialUser);

        return DB::transaction(function () use ($provider, $providerUserId, $email, $name, $avatar, $emailVerified): User {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($account !== null) {
                $previousAvatar = $this->normalizeNullableString($account->avatar);

                $account->forceFill([
                    'email' => $provider === SocialAuth::PROVIDER_TELEGRAM ? null : $email,
                    'avatar' => $avatar,
                ])->save();

                $user = $account->user;

                if ($provider === SocialAuth::PROVIDER_TELEGRAM) {
                    $this->syncTelegramName($user, $name);
                } else {
                    $this->ensureEmailVerified($user);
                }

                $this->socialAvatars->sync($user, $avatar, $previousAvatar);

                return $user->refresh();
            }

            if ($provider === SocialAuth::PROVIDER_TELEGRAM) {
                $user = $this->createTelegramUser($provider, $providerUserId, $name, $avatar);
                $this->socialAvatars->sync($user, $avatar, null);

                return $user->refresh();
            }

            $user = null;

            // Only trust provider emails for account matching when the
            // provider itself verified them, otherwise a provider account
            // with an unverified email could be used to hijack a site
            // account that was registered with that address.
            if ($email !== null && $emailVerified) {
                $user = User::query()->where('email', $email)->first();
            }

            if ($user === null) {
                if ($email === null || ! $emailVerified) {
                    throw ValidationException::withMessages([
                        'social' => [__('Your social account email is not verified with :provider. Please verify it or use another login method.', [
                            'provider' => ucfirst($provider),
                        ])],
                    ]);
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now(),
                    'registration_ip' => request()->ip(),
                ]);
            } else {
                $this->ensureEmailVerified($user);
            }

            SocialAccount::query()->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email' => $email,
                'avatar' => $avatar,
            ]);

            $this->socialAvatars->sync($user, $avatar, null);

            return $user->refresh();
        });
    }

    private function createTelegramUser(string $provider, string $providerUserId, string $name, ?string $avatar): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => null,
            'password' => null,
            'email_verified_at' => null,
            'registration_ip' => request()->ip(),
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->getKey(),
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => null,
            'avatar' => $avatar,
        ]);

        return $user->refresh();
    }

    /**
     * Telegram sends no email, so a display name is the only profile field
     * worth refreshing. The "User" fallback must not wipe a name the person
     * already chose.
     */
    private function syncTelegramName(User $user, string $name): void
    {
        if ($name === 'User' || $user->name === $name) {
            return;
        }

        $user->forceFill([
            'name' => $name,
        ])->save();
    }

    private function ensureEmailVerified(User $user): void
    {
        if ($user->email === null || $user->email_verified_at !== null) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }

    private function isEmailVerified(string $provider, SocialiteUser $socialUser): bool
    {
        if (! $socialUser instanceof AbstractUser) {
            return false;
        }

        $raw = $socialUser->getRaw();

        return match ($provider) {
            'google' => filter_var(
                $raw['email_verified'] ?? $raw['verified_email'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            ),
            'discord' => filter_var(
                $raw['verified'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            ),
            default => false,
        };
    }

    private function resolveName(SocialiteUser $socialUser): string
    {
        $name = $this->normalizeNullableString($socialUser->getName())
            ?? $this->normalizeNullableString($socialUser->getNickname());

        if ($name !== null) {
            return $name;
        }

        $email = $this->normalizeEmail($socialUser->getEmail());

        if ($email !== null) {
            return strstr($email, '@', true) ?: 'User';
        }

        return 'User';
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = $this->normalizeNullableString($email);

        if ($email === null) {
            return null;
        }

        return mb_strtolower($email);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
