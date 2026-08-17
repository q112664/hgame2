<?php

namespace App\Http\Controllers;

use App\Actions\Games\RemoveGameFavorite;
use App\Actions\Games\ToggleGameFavorite;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FavoriteController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('users.favorites', $request->user());
    }

    public function toggle(
        Request $request,
        Game $resource,
        ToggleGameFavorite $toggleGameFavorite,
    ): RedirectResponse {
        $favorited = $toggleGameFavorite($request->user(), $resource);

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
