<?php

namespace App\Models;

use Database\Factories\GameDownloadLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_release_id', 'label', 'url', 'is_active', 'sort_order'])]
class GameDownloadLink extends Model
{
    /** @use HasFactory<GameDownloadLinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(GameRelease::class, 'game_release_id');
    }
}
