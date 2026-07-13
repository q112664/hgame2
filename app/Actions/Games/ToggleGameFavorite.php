<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\User;

class ToggleGameFavorite
{
    public function __invoke(User $user, Game $game): bool
    {
        $changed = $user->favoritedGames()->toggle([$game->id]);

        if ($changed['attached'] !== []) {
            $user->favoritedGames()->updateExistingPivot($game->id, [
                'downloads_seen_at' => $game->downloads_updated_at ?? now(),
            ]);
        }

        return $changed['attached'] !== [];
    }
}
