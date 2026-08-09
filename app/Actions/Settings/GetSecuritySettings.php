<?php

namespace App\Actions\Settings;

use App\Actions\Auth\UnlinkSocialAccount;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialAuth;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

class GetSecuritySettings
{
    /**
     * @return array{
     *     canManageTwoFactor: bool,
     *     canManagePasskeys: bool,
     *     hasPassword: bool,
     *     passkeys: array<int, array{id: int, name: string, authenticator: ?string, created_at_diff: string, last_used_at_diff: ?string}>,
     *     passwordRules: string,
     *     socialConnections: list<array{
     *         provider: string,
     *         label: string,
     *         available: bool,
     *         linked: bool,
     *         email: string|null,
     *         canUnlink: bool
     *     }>,
     *     twoFactorEnabled?: bool,
     *     requiresConfirmation?: bool
     * }
     */
    public function for(User $user): array
    {
        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();
        $unlinker = app(UnlinkSocialAccount::class);
        $linkedAccounts = $user->socialAccounts()
            ->get()
            ->keyBy('provider');

        $props = [
            'canManageTwoFactor' => $canManageTwoFactor,
            'canManagePasskeys' => Features::canManagePasskeys(),
            'hasPassword' => $user->hasPassword(),
            'passkeys' => Features::canManagePasskeys()
                ? $user->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn (Passkey $passkey): array => [
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
            'socialConnections' => array_values(collect(SocialAuth::PROVIDERS)
                ->map(function (string $provider) use ($linkedAccounts, $unlinker, $user): ?array {
                    /** @var SocialAccount|null $account */
                    $account = $linkedAccounts->get($provider);
                    $available = SocialAuth::isEnabled($provider);
                    $linked = $account !== null;

                    if (! $available && ! $linked) {
                        return null;
                    }

                    return [
                        'provider' => $provider,
                        'label' => ucfirst($provider),
                        'available' => $available,
                        'linked' => $linked,
                        'email' => $account?->email,
                        'canUnlink' => $linked && $unlinker->canUnlink($user, $provider),
                    ];
                })
                ->filter()
                ->values()
                ->all()),
        ];

        if ($canManageTwoFactor) {
            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return $props;
    }
}
