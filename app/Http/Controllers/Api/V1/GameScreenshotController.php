<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\AddGameScreenshot;
use App\Actions\Games\DeleteGameScreenshot;
use App\Actions\Games\UpdateGameScreenshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGameScreenshotRequest;
use App\Http\Requests\Api\V1\UpdateGameScreenshotRequest;
use App\Models\Game;
use App\Models\GameScreenshot;
use App\Support\GameApiPayload;
use Illuminate\Http\JsonResponse;

class GameScreenshotController extends Controller
{
    public function store(
        StoreGameScreenshotRequest $request,
        Game $game,
        AddGameScreenshot $addGameScreenshot,
    ): JsonResponse {
        $game = $addGameScreenshot($game, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ], 201);
    }

    public function update(
        UpdateGameScreenshotRequest $request,
        Game $game,
        GameScreenshot $screenshot,
        UpdateGameScreenshot $updateGameScreenshot,
    ): JsonResponse {
        $game = $updateGameScreenshot($game, $screenshot, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }

    public function destroy(
        Game $game,
        GameScreenshot $screenshot,
        DeleteGameScreenshot $deleteGameScreenshot,
    ): JsonResponse {
        $game = $deleteGameScreenshot($game, $screenshot);

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }
}
