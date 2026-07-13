<?php

namespace App\Actions\Games;

use App\Models\Game;
use Illuminate\Http\Request;

class RecordGameView
{
    public function __invoke(Request $request, Game $game): void
    {
        $sessionKey = 'viewed_games.'.$game->id;

        if ($request->session()->has($sessionKey)) {
            return;
        }

        $request->session()->put($sessionKey, true);

        $game->increment('views_count');
    }
}
