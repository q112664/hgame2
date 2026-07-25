<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameRating;
use App\Models\User;

class UpsertGameRating
{
    public function __invoke(User $user, Game $game, int $score): GameRating
    {
        return GameRating::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'game_id' => $game->id,
            ],
            [
                'score' => $score,
            ],
        );
    }
}
