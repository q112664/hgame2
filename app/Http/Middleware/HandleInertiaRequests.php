<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\TaxonomyDirectory;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => Setting::siteLogoText(),
            'siteTitle' => Setting::siteTitle(),
            'seo' => Setting::seo(),
            'siteLogo' => Setting::siteLogo(),
            'navigationMenu' => Setting::navigationMenu(),
            'footerLinks' => Setting::footerLinks(),
            'taxonomyNav' => TaxonomyDirectory::navigation(),
            'turnstile' => Turnstile::frontendConfig(),
            'auth' => [
                'user' => $request->user(),
            ],
            'authModal' => $request->user() ? null : [
                'canRegister' => Features::enabled(Features::registration()),
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canUsePasskeys' => Features::canManagePasskeys(),
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
                'turnstile' => Turnstile::frontendConfig(),
            ],
            'notificationSummary' => [
                'unreadCount' => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
