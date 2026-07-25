<?php

namespace App\Http\Controllers;

use App\Actions\Games\ClearGameRating;
use App\Actions\Games\UpsertGameRating;
use App\Http\Requests\UpsertGameRatingRequest;
use App\Models\Game;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GameRatingController extends Controller
{
    public function store(
        UpsertGameRatingRequest $request,
        Game $resource,
        UpsertGameRating $upsertGameRating,
    ): RedirectResponse {
        $this->ensureRatingsEnabled();

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
        $this->ensureRatingsEnabled();

        $clearGameRating($request->user(), $resource);

        return back();
    }

    private function ensureRatingsEnabled(): void
    {
        if (! Setting::ratingsEnabled()) {
            throw new NotFoundHttpException;
        }
    }
}
