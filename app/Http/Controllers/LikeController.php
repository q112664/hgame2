<?php

namespace App\Http\Controllers;

use App\Actions\Games\ToggleGameLike;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LikeController extends Controller
{
    public function toggle(
        Request $request,
        Game $resource,
        ToggleGameLike $toggleGameLike,
    ): RedirectResponse {
        $result = $toggleGameLike($request->user(), $resource);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['liked']
                ? __('Liked.')
                : __('Like removed.'),
        ]);

        return back();
    }
}
