<?php

namespace App\Http\Controllers;

use App\Actions\Games\RemoveGameFavorite;
use App\Actions\Games\ToggleGameFavorite;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FavoriteController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('users.favorites', $request->user());
    }

    /**
     * Current state for the background revalidation the detail page runs on mount
     * and on history navigation, so a restored page never shows a stale heart.
     */
    public function show(Request $request, Game $resource): JsonResponse
    {
        return response()->json([
            'favorited' => $request->user()
                ->favoritedGames()
                ->whereKey($resource->getKey())
                ->exists(),
        ]);
    }

    public function toggle(
        Request $request,
        Game $resource,
        ToggleGameFavorite $toggleGameFavorite,
    ): JsonResponse|RedirectResponse {
        $favorited = $toggleGameFavorite($request->user(), $resource);

        // The detail page toggles through a background request so the button does
        // not wait on a full Inertia visit (and its progress bar).
        if ($request->wantsJson()) {
            return response()->json([
                'favorited' => $favorited,
                'message' => $favorited
                    ? __('Added to favorites.')
                    : __('Removed from favorites.'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $favorited
                ? __('Added to favorites.')
                : __('Removed from favorites.'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        Game $resource,
        RemoveGameFavorite $removeGameFavorite,
    ): RedirectResponse {
        $removeGameFavorite($request->user(), $resource);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed from favorites.'),
        ]);

        return back();
    }
}
