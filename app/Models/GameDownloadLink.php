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

    protected static function booted(): void
    {
        static::saving(function (GameDownloadLink $link): void {
            $link->is_active = true;

            if (blank($link->label)) {
                $host = parse_url((string) $link->url, PHP_URL_HOST);
                $link->label = is_string($host) && $host !== '' ? $host : 'Download';
            }
        });

        $touchGame = function (GameDownloadLink $link): void {
            $link->loadMissing('release.game');
            $link->release?->game?->touchDownloadsUpdatedAt();
        };

        static::saved($touchGame);
        static::deleted($touchGame);
    }

    /** @return BelongsTo<GameRelease, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(GameRelease::class, 'game_release_id');
    }
}
