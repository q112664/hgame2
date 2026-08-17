<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\GetSecuritySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Support\PageSeo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Fortify\Features;

class SettingsController extends Controller
{
    public function profile(TwoFactorAuthenticationRequest $request, GetSecuritySettings $securitySettings): Response
    {
        return $this->renderSettings(
            $request,
            'profile',
            $this->securitySettingsProps($request, $securitySettings),
        );
    }

    public function security(TwoFactorAuthenticationRequest $request, GetSecuritySettings $securitySettings): Response
    {
        return $this->renderSettings(
            $request,
            'security',
            $this->securitySettingsProps($request, $securitySettings),
        );
    }

    public function confirmSecurity(Request $request, ConfirmPassword $confirmPassword, StatefulGuard $guard): RedirectResponse
    {
        if (! $confirmPassword($guard, $request->user(), $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return to_route('security.edit');
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderSettings(Request $request, string $activeTab, array $props): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/index', [
            'activeTab' => $activeTab,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'pageSeo' => PageSeo::noindex('Settings'),
            ...$props,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function securitySettingsProps(TwoFactorAuthenticationRequest $request, GetSecuritySettings $securitySettings): array
    {
        if ($this->requiresPasswordConfirmation()) {
            return ['requiresPasswordConfirmation' => true];
        }

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();
        }

        /** @var User $user */
        $user = $request->user();

        return [
            'requiresPasswordConfirmation' => false,
            ...$securitySettings->for($user),
        ];
    }

    private function requiresPasswordConfirmation(): bool
    {
        // Security settings are not gated behind a second password prompt.
        return false;
    }
}
