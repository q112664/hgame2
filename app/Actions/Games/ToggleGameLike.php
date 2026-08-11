<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleGameLike
{
    /**
     * Toggle whether the user likes the game.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function __invoke(User $user, Game $game): array
    {
        return DB::transaction(function () use ($user, $game): array {
            $changed = $user->likedGames()->toggle([$game->id]);
            $liked = $changed['attached'] !== [];

            if ($liked) {
                $game->increment('likes_count');
            } elseif ($game->likes_count > 0) {
                $game->decrement('likes_count');
            }

            $game->refresh();

            return [
                'liked' => $liked,
                'likes_count' => (int) $game->likes_count,
            ];
        });
    }
}
