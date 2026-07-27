<?php

namespace App\Actions\Games;

use App\Models\Game;
use Illuminate\Http\Request;

class RecordGameView
{
    public const string TabNavigationHeader = 'X-Resource-Tab-Nav';

    /**
     * Record a view on every full page visit / refresh.
     *
     * Inertia navigations between tabs of the same resource never count
     * (Details ↔ Downloads ↔ Gallery), including the hero Download button.
     */
    public function __invoke(Request $request, Game $game): void
    {
        if ($this->shouldSkip($request, $game)) {
            return;
        }

        $game->increment('views_count');
    }

    private function shouldSkip(Request $request, Game $game): bool
    {
        if (! $this->isInertiaRequest($request)) {
            return false;
        }

        // Frontend tab links send this header so counting does not depend on Referer.
        if ($this->isExplicitTabNavigation($request)) {
            return true;
        }

        return $this->isSameResourceReferer($request, $game);
    }

    private function isInertiaRequest(Request $request): bool
    {
        $header = $request->header('X-Inertia');

        if ($header === null || $header === '') {
            return false;
        }

        return filter_var($header, FILTER_VALIDATE_BOOLEAN) || $header === '1';
    }

    private function isExplicitTabNavigation(Request $request): bool
    {
        $header = $request->header(self::TabNavigationHeader);

        if ($header === null || $header === '') {
            return false;
        }

        return filter_var($header, FILTER_VALIDATE_BOOLEAN) || $header === '1';
    }

    /**
     * Fallback when older clients omit the tab header: treat same-resource
     * Inertia visits (via Referer) as tab switches.
     */
    private function isSameResourceReferer(Request $request, Game $game): bool
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return false;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        $path = rawurldecode($path);

        return (bool) preg_match(
            '#^/resources/'.preg_quote($game->slug, '#').'(?:/|$)#',
            $path,
        );
    }
}
