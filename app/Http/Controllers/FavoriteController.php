<?php

namespace App\Http\Controllers;

use App\Actions\Games\ToggleGameFavorite;
use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    public function index(Request $request): Response
    {
        $games = $request->user()
            ->favoritedGames()
            ->published()
            ->with($this->cardRelations())
            ->orderByPivot('created_at', 'desc')
            ->get();

        $resources = $games
            ->map(fn (Game $game): array => [
                ...GamePresenter::card($game),
                'hasDownloadUpdate' => $game->hasUnreadDownloadUpdate(),
            ])
            ->values();

        return Inertia::render('favorites', [
            'resources' => $resources,
            'downloadUpdateCount' => $resources
                ->where('hasDownloadUpdate', true)
                ->count(),
        ]);
    }

    public function toggle(
        Request $request,
        string $resource,
        ToggleGameFavorite $toggleGameFavorite,
    ): RedirectResponse {
        $game = Game::query()
            ->published()
            ->where('slug', $resource)
            ->firstOrFail();

        $toggleGameFavorite($request->user(), $game);

        return back();
    }

    /** @return array<string, callable> */
    private function cardRelations(): array
    {
        return [
            'category' => fn ($query) => $query->select(['id', 'name']),
            'tags' => fn ($query) => $query->select(['tags.id', 'name']),
            'releases' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('downloadLinks', fn ($links) => $links->where('is_active', true))
                ->with([
                    'platforms:id,name,slug',
                    'languages:id,name',
                    'downloadLinks' => fn ($links) => $links->where('is_active', true),
                ]),
        ];
    }
}
