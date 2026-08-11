<?php

namespace App\Http\Controllers;

use App\Actions\Games\ToggleGameLike;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(
        Request $request,
        Game $resource,
        ToggleGameLike $toggleGameLike,
    ): RedirectResponse {
        $toggleGameLike($request->user(), $resource);

        return back();
    }
}
