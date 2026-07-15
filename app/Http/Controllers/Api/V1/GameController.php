<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\PublishGame;
use App\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGameRequest;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    public function store(StoreGameRequest $request, PublishGame $publishGame): JsonResponse
    {
        $game = $publishGame->handle($request->validated());

        return response()->json([
            'data' => $this->payload($game),
        ], 201);
    }

    public function show(Game $game): JsonResponse
    {
        $game->load([
            'category',
            'tags',
            'screenshots',
            'releases.platforms',
            'releases.languages',
            'releases.downloadLinks',
        ]);

        return response()->json([
            'data' => $this->payload($game),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Game $game): array
    {
        $status = $game->getAttribute('status');

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'status' => $status instanceof GameStatus
                ? $status->value
                : (string) $status,
            'url' => route('resources.details', $game),
            'screenshots_count' => $game->screenshots->count(),
            'releases_count' => $game->releases->count(),
        ];
    }
}
