<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\User;

class RemoveGameFavorite
{
    public function __invoke(User $user, Game $game): void
    {
        $user->favoritedGames()->detach($game->id);
    }
}
