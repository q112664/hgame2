<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveSocialUser
{
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
                $account->forceFill([
                    'email' => $email,
                    'avatar' => $avatar,
                ])->save();

                $user = $account->user;
                $this->ensureEmailVerified($user);

                return $user;
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

            return $user->refresh();
        });
    }

    private function ensureEmailVerified(User $user): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }

    private function isEmailVerified(string $provider, SocialiteUser $socialUser): bool
    {
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
