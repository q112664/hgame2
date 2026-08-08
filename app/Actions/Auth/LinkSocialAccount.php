<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class LinkSocialAccount
{
    public function handle(User $user, string $provider, SocialiteUser $socialUser): SocialAccount
    {
        $providerUserId = (string) $socialUser->getId();

        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'social' => [__('Unable to link this social account. Please try again.')],
            ]);
        }

        $email = $this->normalizeEmail($socialUser->getEmail());
        $avatar = $this->normalizeNullableString($socialUser->getAvatar());

        return DB::transaction(function () use ($user, $provider, $providerUserId, $email, $avatar): SocialAccount {
            $existing = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($existing !== null) {
                if ((int) $existing->user_id !== (int) $user->getKey()) {
                    throw ValidationException::withMessages([
                        'social' => [__('This social account is already linked to another user.')],
                    ]);
                }

                $existing->forceFill([
                    'email' => $email,
                    'avatar' => $avatar,
                ])->save();

                return $existing->refresh();
            }

            $alreadyLinked = SocialAccount::query()
                ->where('user_id', $user->getKey())
                ->where('provider', $provider)
                ->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages([
                    'social' => [__('You already have a :provider account linked. Unlink it first.', [
                        'provider' => ucfirst($provider),
                    ])],
                ]);
            }

            return SocialAccount::query()->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email' => $email,
                'avatar' => $avatar,
            ]);
        });
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
