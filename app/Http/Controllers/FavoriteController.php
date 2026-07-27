<?php

namespace App\Http\Controllers;

use App\Actions\Games\RemoveGameFavorite;
use App\Actions\Games\ToggleGameFavorite;
use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    private const PER_PAGE = 8;

    public function index(Request $request): Response
    {
        $resources = $request->user()
            ->favoritedGames()
            ->published()
            ->withCardData()
            ->orderByPivot('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Game $game): array => [
                ...GamePresenter::card($game),
                'hasDownloadUpdate' => $game->hasUnreadDownloadUpdate(),
            ]);

        $downloadUpdateCount = $request->user()
            ->favoritedGames()
            ->published()
            ->whereNotNull('games.downloads_updated_at')
            ->whereRaw(
                'games.downloads_updated_at > COALESCE(favorites.downloads_seen_at, favorites.created_at)',
            )
            ->count();

        return Inertia::render('favorites', [
            'resources' => $resources,
            'downloadUpdateCount' => $downloadUpdateCount,
        ]);
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
