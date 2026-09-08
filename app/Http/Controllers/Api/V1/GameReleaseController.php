<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\DeleteGameRelease;
use App\Actions\Games\SaveGameReleaseFromApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGameReleaseRequest;
use App\Http\Requests\Api\V1\UpdateGameReleaseRequest;
use App\Models\Game;
use App\Models\GameRelease;
use App\Support\GameApiPayload;
use Illuminate\Http\JsonResponse;

class GameReleaseController extends Controller
{
    public function store(
        StoreGameReleaseRequest $request,
        Game $game,
        SaveGameReleaseFromApi $saveGameReleaseFromApi,
    ): JsonResponse {
        $game = $saveGameReleaseFromApi->create($game, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ], 201);
    }

    public function update(
        UpdateGameReleaseRequest $request,
        Game $game,
        GameRelease $release,
        SaveGameReleaseFromApi $saveGameReleaseFromApi,
    ): JsonResponse {
        $game = $saveGameReleaseFromApi->update($game, $release, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }

    public function destroy(
        Game $game,
        GameRelease $release,
        DeleteGameRelease $deleteGameRelease,
    ): JsonResponse {
        $game = $deleteGameRelease($game, $release);

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }
}
