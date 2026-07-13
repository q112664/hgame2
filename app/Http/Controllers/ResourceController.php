<?php

namespace App\Http\Controllers;

use App\Actions\Games\MarkFavoriteDownloadsSeen;
use App\Actions\Games\RecordGameView;
use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(
        private RecordGameView $recordGameView,
    ) {}

    public function show(string $resource): RedirectResponse
    {
        $this->findGame($resource);

        return to_route('resources.details', ['resource' => $resource]);
    }

    public function details(Request $request, string $resource): Response
    {
        return $this->renderResource($request, $resource, 'details');
    }

    public function downloads(
        Request $request,
        string $resource,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): Response {
        $game = $this->findGame($resource);
        ($this->recordGameView)($request, $game);

        if (auth()->check()) {
            $markFavoriteDownloadsSeen(auth()->user(), $game->id);
        }

        return Inertia::render('resources/show', [
            'activeTab' => 'downloads',
            'resource' => $this->presentResource($game),
        ]);
    }

    public function screenshots(Request $request, string $resource): Response
    {
        return $this->renderResource($request, $resource, 'screenshots');
    }

    private function renderResource(Request $request, string $resource, string $activeTab): Response
    {
        $game = $this->findGame($resource);
        ($this->recordGameView)($request, $game);

        return Inertia::render('resources/show', [
            'activeTab' => $activeTab,
            'resource' => $this->presentResource($game),
        ]);
    }

    private function findGame(string $resource): Game
    {
        return Game::query()
            ->published()
            ->where('slug', $resource)
            ->with([
                'category:id,name',
                'tags:id,name',
                'screenshots' => fn ($query) => $query->orderBy('sort_order'),
                'releases' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('downloadLinks', fn ($links) => $links->where('is_active', true))
                    ->with([
                        'platforms:id,name,slug',
                        'languages:id,name',
                        'downloadLinks' => fn ($links) => $links
                            ->where('is_active', true)
                            ->orderBy('sort_order'),
                    ])
                    ->orderBy('sort_order'),
            ])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentResource(Game $game): array
    {
        return [
            ...GamePresenter::detail($game),
            'isFavorited' => auth()->user()
                ?->favoritedGames()
                ->where('games.id', $game->id)
                ->exists() ?? false,
            'adminEditUrl' => auth()->user()?->is_admin
                ? GameResource::getUrl('edit', ['record' => $game], panel: 'admin')
                : null,
        ];
    }
}
