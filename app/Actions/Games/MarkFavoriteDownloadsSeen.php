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
        $notifications = $user->unreadNotifications()
            ->where('type', 'favorite.downloads_updated');

        if ($notifications->getQuery()->getConnection()->getDriverName() === 'pgsql') {
            $notifications->whereRaw("(data::jsonb ->> 'game_id') = ?", [(string) $gameId]);
        } else {
            $notifications->where('data->game_id', $gameId);
        }

        $notifications->update(['read_at' => $seenAt ?? now()]);
    }
}
