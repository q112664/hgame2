<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\DeleteGameDetailVersion;
use App\Actions\Games\UpsertGameDetailVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertGameDetailVersionRequest;
use App\Models\Game;
use App\Support\GameApiPayload;
use Illuminate\Http\JsonResponse;

class GameDetailVersionController extends Controller
{
    public function upsert(
        UpsertGameDetailVersionRequest $request,
        Game $game,
        string $language,
        UpsertGameDetailVersion $upsertGameDetailVersion,
    ): JsonResponse {
        $game = $upsertGameDetailVersion($game, $language, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }

    public function destroy(
        Game $game,
        string $language,
        DeleteGameDetailVersion $deleteGameDetailVersion,
    ): JsonResponse {
        $game = $deleteGameDetailVersion($game, $language);

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }
}
