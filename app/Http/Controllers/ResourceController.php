<?php

namespace App\Http\Controllers;

use App\Actions\Games\ListPublishedGames;
use App\Actions\Games\MarkFavoriteDownloadsSeen;
use App\Actions\Games\RecordGameView;
use App\Filament\Resources\Games\GameResource;
use App\Http\Requests\ListResourcesRequest;
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

    public function index(ListResourcesRequest $request, ListPublishedGames $listPublishedGames): Response
    {
        return Inertia::render('resources/index', $listPublishedGames($request->filters()));
    }

    public function show(string $resource): RedirectResponse
    {
        Game::query()
            ->published()
            ->where('slug', $resource)
            ->firstOrFail();

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
        $game = $this->findGame($resource, includeScreenshots: false, includeDownloadLinks: true);
        ($this->recordGameView)($request, $game);

        if (auth()->check()) {
            $markFavoriteDownloadsSeen(auth()->user(), $game->id);
        }

        return Inertia::render('resources/show', [
            'activeTab' => 'downloads',
            'resource' => $this->presentResource($game, includeScreenshots: false, includeReleases: true),
        ]);
    }

    public function screenshots(Request $request, string $resource): Response
    {
        return $this->renderResource($request, $resource, 'screenshots');
    }

    private function renderResource(Request $request, string $resource, string $activeTab): Response
    {
        $game = $this->findGame(
            $resource,
            includeScreenshots: $activeTab === 'screenshots',
            includeDownloadLinks: $activeTab === 'downloads',
        );
        ($this->recordGameView)($request, $game);

        return Inertia::render('resources/show', [
            'activeTab' => $activeTab,
            'resource' => $this->presentResource(
                $game,
                includeScreenshots: $activeTab === 'screenshots',
                includeReleases: $activeTab === 'downloads',
            ),
        ]);
    }

    private function findGame(
        string $resource,
        bool $includeScreenshots,
        bool $includeDownloadLinks,
    ): Game {
        $with = [
            'category:id,name',
            'tags:id,name',
            'releases' => $includeDownloadLinks
                ? fn ($query) => $query->withDownloadDetails()
                : fn ($query) => $query->withCardSummary(),
        ];

        if ($includeScreenshots) {
            $with['screenshots'] = fn ($query) => $query->orderBy('sort_order');
        }

        return Game::query()
            ->published()
            ->where('slug', $resource)
            ->with($with)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentResource(
        Game $game,
        bool $includeScreenshots,
        bool $includeReleases,
    ): array {
        return [
            ...GamePresenter::detail(
                $game,
                includeScreenshots: $includeScreenshots,
                includeReleases: $includeReleases,
            ),
            'hasDownloads' => $game->releases->isNotEmpty(),
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
