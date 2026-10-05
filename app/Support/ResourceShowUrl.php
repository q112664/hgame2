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
     * The tab is a query parameter rather than a fragment so it survives the
     * redirects that land back here.
     *
     * @param  'details'|'downloads'|'screenshots'  $tab
     * @param  array<string, mixed>  $query
     */
    public static function tab(
        Game|string $game,
        string $tab,
        array $query = [],
        bool $absolute = true,
    ): string {
        if (! in_array($tab, ['downloads', 'screenshots'], true)) {
            unset($query['tab']);

            return self::details($game, $query, $absolute);
        }

        $query['tab'] = $tab;

        return self::details($game, $query, $absolute);
    }
}
