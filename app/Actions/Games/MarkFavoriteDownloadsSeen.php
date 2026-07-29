<?php

namespace App\Actions\Games;

use App\Models\User;
use Illuminate\Support\Carbon;

class MarkFavoriteDownloadsSeen
{
    public function __invoke(User $user, int $gameId, ?Carbon $seenAt = null): void
    {
        if (! $user->favoritedGames()->where('games.id', $gameId)->exists()) {
            return;
        }

        $user->favoritedGames()->updateExistingPivot($gameId, [
            'downloads_seen_at' => $seenAt ?? now(),
        ]);

        // Keep the general notification center in sync with favorites badges.
        $user->unreadNotifications()
            ->where('type', 'favorite.downloads_updated')
            ->where('data->game_id', $gameId)
            ->update(['read_at' => $seenAt ?? now()]);
    }
}
