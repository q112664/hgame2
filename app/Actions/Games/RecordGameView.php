<?php

namespace App\Actions\Games;

use App\Models\Game;
use Illuminate\Http\Request;

class RecordGameView
{
    /**
     * Record a view on every full page visit / refresh.
     *
     * Inertia navigations between tabs of the same resource never count
     * (Details ↔ Downloads ↔ Gallery).
     */
    public function __invoke(Request $request, Game $game): void
    {
        if ($this->isSameResourceInertiaNavigation($request, $game)) {
            return;
        }

        $game->increment('views_count');
    }

    /**
     * Tab switches are Inertia visits whose referer is already this resource.
     */
    private function isSameResourceInertiaNavigation(Request $request, Game $game): bool
    {
        if ($request->header('X-Inertia') !== 'true') {
            return false;
        }

        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return false;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return (bool) preg_match(
            '#^/resources/'.preg_quote($game->slug, '#').'(?:/|$)#',
            $path,
        );
    }
}
