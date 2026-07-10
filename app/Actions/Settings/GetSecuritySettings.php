<?php

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

class GetSecuritySettings
{
    /**
     * @return array{
     *     canManageTwoFactor: bool,
     *     canManagePasskeys: bool,
     *     passkeys: array<int, array{id: int, name: string, authenticator: ?string, created_at_diff: string, last_used_at_diff: ?string}>,
     *     passwordRules: string,
     *     twoFactorEnabled?: bool,
     *     requiresConfirmation?: bool
     * }
     */
    public function for(User $user): array
    {
        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        $props = [
            'canManageTwoFactor' => $canManageTwoFactor,
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if ($canManageTwoFactor) {
            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return $props;
    }
}
