<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\SaveGameFromApi;
use App\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexGamesRequest;
use App\Http\Requests\Api\V1\StoreGameRequest;
use App\Http\Requests\Api\V1\UpdateGameRequest;
use App\Models\Game;
use App\Support\GameSource;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function index(IndexGamesRequest $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Game::query()
            ->with(['category', 'screenshots', 'releases'])
            ->withCount(['screenshots', 'releases'])
            ->latest('id');

        if ($request->filled('q')) {
            $query->matchingSearch((string) $request->string('q'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('category')) {
            $category = (string) $request->string('category');

            $query->whereHas(
                'category',
                fn (Builder $builder): Builder => $builder
                    ->where('slug', $category)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($category)]),
            );
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Game $game): array => $this->summaryPayload($game))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreGameRequest $request, SaveGameFromApi $saveGameFromApi): JsonResponse
    {
        $game = $saveGameFromApi->create($request->validated());

        return response()->json([
            'data' => $this->detailPayload($game),
        ], 201);
    }

    public function show(Game $game): JsonResponse
    {
        $game->load([
            'category',
            'tags',
            'screenshots',
            'releases.contributor',
            'releases.platforms',
            'releases.languages',
            'releases.downloadLinks',
            'detailTranslations.language',
        ]);

        return response()->json([
            'data' => $this->detailPayload($game),
        ]);
    }

    public function update(
        UpdateGameRequest $request,
        Game $game,
        SaveGameFromApi $saveGameFromApi,
    ): JsonResponse {
        $game = $saveGameFromApi->update($game, $request->validated());

        return response()->json([
            'data' => $this->detailPayload($game),
        ]);
    }

    public function destroy(Game $game): JsonResponse
    {
        $slug = $game->slug;
        $game->delete();

        return response()->json([
            'data' => [
                'id' => $slug,
                'deleted' => true,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(Game $game): array
    {
        $status = $game->getAttribute('status');

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'status' => $status instanceof GameStatus
                ? $status->value
                : (string) $status,
            'category' => $game->category?->name,
            'developer' => $game->developer,
            'url' => route('resources.details', $game),
            'cover_url' => Media::url($game->cover_path ?: $game->cover_url),
            'published_at' => $game->published_at?->toIso8601String(),
            'screenshots_count' => $game->screenshots_count
                ?? $game->screenshots->count(),
            'releases_count' => $game->releases_count
                ?? $game->releases->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(Game $game): array
    {
        $status = $game->getAttribute('status');

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'status' => $status instanceof GameStatus
                ? $status->value
                : (string) $status,
            'category' => $game->category?->name,
            'tags' => $game->tags->pluck('name')->values()->all(),
            'developer' => $game->developer,
            'source_name' => $game->source_name,
            'source_id' => $game->source_id,
            'source_url' => $game->source_url,
            'source' => GameSource::present(
                $game->source_name,
                $game->source_id,
                $game->source_url,
            ),
            'release_date' => $game->release_date?->toDateString(),
            'description' => $game->description,
            'detail_versions' => $game->relationLoaded('detailTranslations')
                ? $game->detailTranslations
                    ->map(fn ($translation): array => [
                        'language' => [
                            'name' => $translation->language?->name,
                            'code' => $translation->language?->code,
                        ],
                        'description' => $translation->description,
                        'sort_order' => (int) $translation->sort_order,
                    ])
                    ->values()
                    ->all()
                : [],
            'cover_url' => Media::url($game->cover_path ?: $game->cover_url),
            'published_at' => $game->published_at?->toIso8601String(),
            'url' => route('resources.details', $game),
            'screenshots' => $game->screenshots
                ->map(fn ($screenshot): string => Media::url($screenshot->path ?: $screenshot->url))
                ->values()
                ->all(),
            'releases' => $game->releases
                ->map(fn ($release): array => [
                    'title' => $release->title,
                    'platforms' => $release->platforms->pluck('name')->values()->all(),
                    'languages' => $release->languages->pluck('name')->values()->all(),
                    'version' => $release->version,
                    'file_size' => $release->file_size,
                    'description' => $release->description,
                    'is_active' => (bool) $release->is_active,
                    'published_at' => $release->published_at?->toIso8601String(),
                    'contributor' => $release->relationLoaded('contributor') && $release->contributor !== null
                        ? [
                            'name' => $release->contributor->name,
                            'email' => $release->contributor->email,
                        ]
                        : null,
                    'download_links' => $release->downloadLinks
                        ->pluck('url')
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'screenshots_count' => $game->screenshots->count(),
            'releases_count' => $game->releases->count(),
        ];
    }
}
