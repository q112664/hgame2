<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialAuth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

class UnlinkSocialAccount
{
    public function handle(User $user, string $provider): void
    {
        if (! SocialAuth::isSupported($provider)) {
            throw ValidationException::withMessages([
                'social' => [__('Unsupported social provider.')],
            ]);
        }

        $account = SocialAccount::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'social' => [__('That social account is not linked.')],
            ]);
        }

        if (! $this->canUnlink($user, $provider)) {
            throw ValidationException::withMessages([
                'social' => [__('Add a password or another sign-in method before unlinking this account.')],
            ]);
        }

        $account->delete();
    }

    public function canUnlink(User $user, string $provider): bool
    {
        if (! $user->hasPassword()
            && ! $this->hasOtherSocialAccounts($user, $provider)
            && ! $this->hasPasskeys($user)
        ) {
            return false;
        }

        return SocialAccount::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->exists();
    }

    private function hasOtherSocialAccounts(User $user, string $provider): bool
    {
        return SocialAccount::query()
            ->where('user_id', $user->getKey())
            ->where('provider', '!=', $provider)
            ->get()
            ->contains(fn (SocialAccount $account): bool => SocialAuth::isEnabled($account->provider));
    }

    private function hasPasskeys(User $user): bool
    {
        if (! Features::canManagePasskeys()) {
            return false;
        }

        return $user->passkeys()->exists();
    }
}
