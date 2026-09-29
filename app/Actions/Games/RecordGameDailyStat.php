<?php

namespace App\Actions\Games;

use App\GameMetric;
use App\Models\Game;
use App\Models\GameDailyStat;

class RecordGameDailyStat
{
    /**
     * Move today's counter for a game, creating the row on the day's first hit.
     *
     * The row is written in app time and read back as the same calendar date, so
     * the day boundary is the site's, not the database's.
     */
    public function __invoke(Game $game, GameMetric $metric): void
    {
        $date = today()->toDateString();

        // Two simultaneous first hits of the day both see no row and both try to
        // insert; the unique key on (game_id, date) rejects one of them, and the
        // create-or-first semantics of firstOrCreate retry as a read.
        $stat = GameDailyStat::query()->firstOrCreate([
            'game_id' => $game->getKey(),
            'date' => $date,
        ]);

        $stat->increment($metric->value);
    }
}
