<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\IntendedUrl;
use App\Support\PageSeo;
use App\Support\SocialAuth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            IntendedUrl::remember($request);

            return Inertia::render('auth/login', [
                'canRegister' => Features::enabled(Features::registration()),
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canUsePasskeys' => Features::canManagePasskeys(),
                'socialProviders' => SocialAuth::enabledProviders(),
                'status' => $request->session()->get('status'),
                'pageSeo' => PageSeo::noindex('Log in'),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'pageSeo' => PageSeo::noindex('Reset password'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'pageSeo' => PageSeo::noindex('Forgot password'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            'pageSeo' => PageSeo::noindex('Email verification'),
        ]));

        Fortify::registerView(function (Request $request) {
            IntendedUrl::remember($request);

            return Inertia::render('auth/register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
                'socialProviders' => SocialAuth::enabledProviders(),
                'pageSeo' => PageSeo::noindex('Register'),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge', [
            'pageSeo' => PageSeo::noindex('Two-factor authentication'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password', [
            'pageSeo' => PageSeo::noindex('Confirm password'),
        ]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
