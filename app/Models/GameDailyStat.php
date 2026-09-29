<?php

namespace App\Models;

use Database\Factories\GameDailyStatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One game's views and downloads for a single day.
 *
 * These rows start the day the counters were introduced: the lifetime totals on
 * `games` cannot be split back into the days they were earned on, so there is no
 * history before that date.
 *
 * `date` is deliberately a plain `Y-m-d` string rather than a date cast. A
 * daily bucket is a calendar day, not an instant, and casting it made writes
 * store `2026-09-29 00:00:00` while lookups searched for `2026-09-29`, so the
 * day's second hit missed its own row and hit the unique key instead.
 *
 * @property int $game_id
 * @property string $date
 * @property int $views
 * @property int $downloads
 * @property-read Game|null $game
 */
#[Fillable(['game_id', 'date', 'views', 'downloads'])]
class GameDailyStat extends Model
{
    /** @use HasFactory<GameDailyStatFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'downloads' => 'integer',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
