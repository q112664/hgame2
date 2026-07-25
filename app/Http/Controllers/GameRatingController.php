<?php

namespace App\Http\Controllers;

use App\Actions\Games\ClearGameRating;
use App\Actions\Games\UpsertGameRating;
use App\Http\Requests\UpsertGameRatingRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GameRatingController extends Controller
{
    public function store(
        UpsertGameRatingRequest $request,
        Game $resource,
        UpsertGameRating $upsertGameRating,
    ): RedirectResponse {
        $upsertGameRating(
            $request->user(),
            $resource,
            (int) $request->validated('score'),
        );

        return back();
    }

    public function destroy(
        Request $request,
        Game $resource,
        ClearGameRating $clearGameRating,
    ): RedirectResponse {
        $clearGameRating($request->user(), $resource);

        return back();
    }
}
