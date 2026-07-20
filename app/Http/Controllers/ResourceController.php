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
use Illuminate\Http\Response as HttpResponse;
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

    public function random(): RedirectResponse
    {
        $game = Game::query()
            ->published()
            ->inRandomOrder()
            ->first(['id', 'slug']);

        $response = $game === null
            ? to_route('resources.index')
            : to_route('resources.details', $game);

        return $response->withHeaders([
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function show(Game $resource): RedirectResponse
    {
        return to_route('resources.details', ['resource' => $resource]);
    }

    public function details(Request $request, Game $resource): Response
    {
        return $this->renderResource($request, $resource, 'details');
    }

    public function downloads(
        Request $request,
        Game $resource,
    ): Response {
        $game = $this->findGame(
            $resource,
            includeScreenshots: false,
            includeDownloadLinks: true,
            includeTags: false,
        );
        ($this->recordGameView)($request, $game);

        return Inertia::render('resources/show', [
            'activeTab' => 'downloads',
            'resource' => $this->presentResource(
                $game,
                includeScreenshots: false,
                includeReleases: true,
                includeDescription: false,
                includeTags: false,
            ),
        ]);
    }

    public function markDownloadsSeen(
        Request $request,
        Game $resource,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): HttpResponse {
        $markFavoriteDownloadsSeen($request->user(), $resource->id);

        return response()->noContent();
    }

    public function screenshots(Request $request, Game $resource): Response
    {
        return $this->renderResource($request, $resource, 'screenshots');
    }

    private function renderResource(Request $request, Game $resource, string $activeTab): Response
    {
        $includeDetails = $activeTab === 'details';
        $game = $this->findGame(
            $resource,
            includeScreenshots: $activeTab === 'screenshots',
            includeDownloadLinks: $activeTab === 'downloads',
            includeTags: $includeDetails,
        );
        ($this->recordGameView)($request, $game);

        return Inertia::render('resources/show', [
            'activeTab' => $activeTab,
            'resource' => $this->presentResource(
                $game,
                includeScreenshots: $activeTab === 'screenshots',
                includeReleases: $activeTab === 'downloads',
                includeDescription: $includeDetails,
                includeTags: $includeDetails,
            ),
        ]);
    }

    private function findGame(
        Game $resource,
        bool $includeScreenshots,
        bool $includeDownloadLinks,
        bool $includeTags,
    ): Game {
        $with = [
            'category:id,name',
            'releases' => $includeDownloadLinks
                ? fn ($query) => $query->withDownloadDetails()
                : fn ($query) => $query->withCardSummary(),
        ];

        if ($includeTags) {
            $with['tags'] = fn ($query) => $query->select(['tags.id', 'name', 'slug']);
        }

        if ($includeScreenshots) {
            $with['screenshots'] = fn ($query) => $query->orderBy('sort_order');
        }

        return $resource->load($with);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentResource(
        Game $game,
        bool $includeScreenshots,
        bool $includeReleases,
        bool $includeDescription,
        bool $includeTags,
    ): array {
        return [
            ...GamePresenter::detail(
                $game,
                includeScreenshots: $includeScreenshots,
                includeReleases: $includeReleases,
                includeDescription: $includeDescription,
                includeTags: $includeTags,
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
