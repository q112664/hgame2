<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\GameReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Game|null $game
 * @property-read User|null $contributor
 * @property-read EloquentCollection<int, Platform> $platforms
 * @property-read EloquentCollection<int, Language> $languages
 * @property-read EloquentCollection<int, GameDownloadLink> $downloadLinks
 */
#[Fillable([
    'game_id', 'user_id', 'platform_id', 'language_id', 'title', 'version', 'file_size',
    'description', 'published_at', 'is_active', 'sort_order',
])]
class GameRelease extends Model
{
    /** @use HasFactory<GameReleaseFactory> */
    use HasFactory;

    /**
     * Attributes that mean download/package content changed for users.
     * Contributor (user_id) and sort_order intentionally excluded.
     *
     * @var list<string>
     */
    private const DOWNLOAD_TOUCH_ATTRIBUTES = [
        'title',
        'version',
        'file_size',
        'description',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<GameRelease>  $query
     * @return Builder<GameRelease>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $published): void {
                $published
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->whereHas(
                'downloadLinks',
                fn (Builder $links): Builder => $links->where('is_active', true),
            );
    }

    /**
     * @param  Builder<GameRelease>  $query
     * @return Builder<GameRelease>
     */
    public function scopeWithCardSummary(Builder $query): Builder
    {
        return $query
            ->available()
            ->select([
                'id',
                'game_id',
                'user_id',
                'version',
                'sort_order',
                'published_at',
                'created_at',
            ])
            ->with([
                'contributor:id,name,avatar',
                'platforms:id,name,slug',
                'languages:id,name,code',
            ])
            ->orderBy('sort_order');
    }

    /**
     * @param  Builder<GameRelease>  $query
     * @return Builder<GameRelease>
     */
    public function scopeWithDownloadDetails(Builder $query): Builder
    {
        return $query
            ->available()
            ->with([
                'contributor:id,name,avatar',
                'platforms:id,name,slug',
                'languages:id,name,code',
                'downloadLinks' => fn ($links) => $links
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->orderBy('sort_order');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Platform, $this> */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /** @return BelongsToMany<Platform, $this> */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class);
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return BelongsToMany<Language, $this> */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }

    /** @return HasMany<GameDownloadLink, $this> */
    public function downloadLinks(): HasMany
    {
        return $this->hasMany(GameDownloadLink::class)->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        static::saved(function (GameRelease $release): void {
            if (! $release->shouldTouchGameDownloads()) {
                return;
            }

            $release->loadMissing('game');
            $release->game?->touchDownloadsUpdatedAt();
        });

        static::deleted(function (GameRelease $release): void {
            $release->loadMissing('game');
            $release->game?->touchDownloadsUpdatedAt();
        });
    }

    /**
     * True when this save is a new package or download-facing fields changed.
     * Assigning contributor (user_id) or reordering alone does not count.
     */
    public function shouldTouchGameDownloads(): bool
    {
        if ($this->wasRecentlyCreated) {
            return true;
        }

        return $this->wasChanged(self::DOWNLOAD_TOUCH_ATTRIBUTES);
    }
}
