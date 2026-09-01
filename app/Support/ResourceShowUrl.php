<?php

namespace App\Support;

use App\Models\Game;

final class ResourceShowUrl
{
    /**
     * @param  array<string, mixed>  $query
     */
    public static function details(Game|string $game, array $query = [], bool $absolute = true): string
    {
        return route('resources.show', [
            'resource' => $game,
            ...$query,
        ], $absolute);
    }

    /**
     * @param  'details'|'downloads'|'screenshots'|'comments'  $tab
     * @param  array<string, mixed>  $query
     */
    public static function tab(
        Game|string $game,
        string $tab,
        array $query = [],
        bool $absolute = true,
    ): string {
        $url = self::details($game, $query, $absolute);

        return match ($tab) {
            'downloads', 'screenshots', 'comments' => $url.'#'.$tab,
            default => $url,
        };
    }

    public static function comment(Game|string $game, int $commentId, bool $absolute = false): string
    {
        return self::details($game, ['focus' => $commentId], $absolute)
            .'#comment-'.$commentId;
    }
}
