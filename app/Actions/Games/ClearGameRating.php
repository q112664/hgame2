<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\User;

class ClearGameRating
{
    public function __invoke(User $user, Game $game): void
    {
        $user->gameRatings()
            ->where('game_id', $game->id)
            ->delete();
    }
}
