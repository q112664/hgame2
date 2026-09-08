<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\SaveGameFromApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexGamesRequest;
use App\Http\Requests\Api\V1\StoreGameRequest;
use App\Http\Requests\Api\V1\UpdateGameRequest;
use App\Models\Game;
use App\Support\GameApiPayload;
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
                ->map(fn (Game $game): array => GameApiPayload::summary($game))
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
            'data' => GameApiPayload::detail($game),
        ], 201);
    }

    public function show(Game $game): JsonResponse
    {
        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }

    public function update(
        UpdateGameRequest $request,
        Game $game,
        SaveGameFromApi $saveGameFromApi,
    ): JsonResponse {
        $game = $saveGameFromApi->update($game, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
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
}
