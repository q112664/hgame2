<?php

namespace App\Models;

use Database\Factories\GameScreenshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'url', 'path', 'alt', 'sort_order'])]
class GameScreenshot extends Model
{
    /** @use HasFactory<GameScreenshotFactory> */
    use HasFactory;

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
