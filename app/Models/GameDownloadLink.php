<?php

namespace App\Models;

use App\Support\DownloadLinkName;
use Database\Factories\GameDownloadLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read GameRelease|null $release
 */
#[Fillable(['game_release_id', 'label', 'url', 'is_active', 'sort_order'])]
class GameDownloadLink extends Model
{
    /** @use HasFactory<GameDownloadLinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (GameDownloadLink $link): void {
            $link->is_active = true;

            // Every write path — the admin repeater and the publish API alike —
            // reaches this hook, so naming a link lives in one place instead of
            // being repeated by each caller.
            $link->label = DownloadLinkName::resolve($link->label, (string) $link->url);
        });
    }

    /** @return BelongsTo<GameRelease, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(GameRelease::class, 'game_release_id');
    }
}
