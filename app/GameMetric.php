<?php

namespace App;

/**
 * The per-day counters a game page can move. The case value is also the column
 * name on `game_daily_stats`.
 */
enum GameMetric: string
{
    case Views = 'views';
    case Downloads = 'downloads';
}
