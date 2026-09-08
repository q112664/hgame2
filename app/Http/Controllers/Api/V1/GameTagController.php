<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Games\AttachGameTags;
use App\Actions\Games\DetachGameTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachGameTagsRequest;
use App\Models\Game;
use App\Support\GameApiPayload;
use Illuminate\Http\JsonResponse;

class GameTagController extends Controller
{
    public function store(
        AttachGameTagsRequest $request,
        Game $game,
        AttachGameTags $attachGameTags,
    ): JsonResponse {
        $game = $attachGameTags($game, $request->validated());

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ], 201);
    }

    public function destroy(
        Game $game,
        string $tag,
        DetachGameTag $detachGameTag,
    ): JsonResponse {
        $game = $detachGameTag($game, $tag);

        return response()->json([
            'data' => GameApiPayload::detail($game),
        ]);
    }
}
