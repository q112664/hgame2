<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LinkSocialAccount;
use App\Actions\Auth\ResolveSocialUser;
use App\Actions\Auth\UnlinkSocialAccount;
use App\Support\IntendedUrl;
use App\Support\SocialAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SocialAuthController extends Controller
{
    public const string INTENT_LOGIN = 'login';

    public const string INTENT_LINK = 'link';

    public function redirect(Request $request, string $provider): SymfonyRedirectResponse
    {
        $this->ensureProviderEnabled($provider);

        $request->session()->put('social_auth_intent', self::INTENT_LOGIN);
        IntendedUrl::remember($request, overwrite: true);

        return SocialAuth::driver($provider)->redirect();
    }

    public function linkRedirect(Request $request, string $provider): SymfonyRedirectResponse
    {
        $this->ensureProviderEnabled($provider);

        $request->session()->put('social_auth_intent', self::INTENT_LINK);
        $request->session()->put('url.intended', route('security.edit'));

        return SocialAuth::driver($provider)->redirect();
    }

    public function callback(
        Request $request,
        string $provider,
        ResolveSocialUser $resolver,
        LinkSocialAccount $linker,
    ): RedirectResponse {
        $this->ensureProviderEnabled($provider);

        $intent = (string) $request->session()->pull('social_auth_intent', self::INTENT_LOGIN);

        if ($intent === self::INTENT_LINK) {
            return $this->handleLinkCallback($request, $provider, $linker);
        }

        return $this->handleLoginCallback($request, $provider, $resolver);
    }

    public function unlink(Request $request, string $provider, UnlinkSocialAccount $unlinker): RedirectResponse
    {
        try {
            $unlinker->handle($request->user(), $provider);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('security.edit')
                ->withErrors($exception->errors());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':provider account unlinked.', ['provider' => ucfirst($provider)]),
        ]);

        return redirect()->route('security.edit');
    }

    private function handleLoginCallback(
        Request $request,
        string $provider,
        ResolveSocialUser $resolver,
    ): RedirectResponse {
        if ($request->user() !== null) {
            return redirect()->intended(route('home'));
        }

        try {
            $socialUser = SocialAuth::driver($provider)->user();
            $user = $resolver->handle($provider, $socialUser);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors($exception->errors());
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->with('status', __('Social login failed. Please try again or use another method.'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Social credentials are treated as a complete sign-in method:
        // users with two-factor enabled are NOT routed through Fortify's
        // two-factor challenge on this path (see SocialAuthenticationTest
        // "social login bypasses the two-factor challenge for 2FA users").
        return redirect()->intended(route('home'));
    }

    private function handleLinkCallback(
        Request $request,
        string $provider,
        LinkSocialAccount $linker,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()
                ->route('login')
                ->with('status', __('Please log in before linking a social account.'));
        }

        try {
            $socialUser = SocialAuth::driver($provider)->user();
            $linker->handle($user, $provider, $socialUser);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('security.edit')
                ->withErrors($exception->errors());
        } catch (Throwable) {
            return redirect()
                ->route('security.edit')
                ->with('status', __('Unable to link this social account. Please try again.'));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':provider account linked.', ['provider' => ucfirst($provider)]),
        ]);

        return redirect()->route('security.edit');
    }

    private function ensureProviderEnabled(string $provider): void
    {
        if (! SocialAuth::isSupported($provider) || ! SocialAuth::isEnabled($provider)) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
