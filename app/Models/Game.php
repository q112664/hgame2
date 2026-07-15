<?php

namespace App\Models;

use App\Actions\Games\DeleteGameMedia;
use App\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

#[Fillable([
    'category_id', 'title', 'subtitle', 'slug', 'description', 'developer', 'cover_url', 'cover_path',
    'release_date', 'status', 'published_at', 'views_count', 'downloads_count', 'downloads_updated_at',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Game $game): void {
            app(DeleteGameMedia::class)($game);
        });
    }

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'status' => GameStatus::class,
            'published_at' => 'datetime',
            'downloads_updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return HasMany<GameRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(GameRelease::class)->orderBy('sort_order');
    }

    /** @return HasMany<GameScreenshot, $this> */
    public function screenshots(): HasMany
    {
        return $this->hasMany(GameScreenshot::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withPivot('downloads_seen_at')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeWithCardData(Builder $query): Builder
    {
        return $query->with([
            'category:id,name',
            'tags:id,name',
            'releases' => fn ($releases) => $releases->withCardSummary(),
        ]);
    }

    public function touchDownloadsUpdatedAt(): void
    {
        $this->forceFill([
            'downloads_updated_at' => now(),
        ])->saveQuietly();
    }

    public function hasUnreadDownloadUpdate(): bool
    {
        $updatedAt = $this->getAttribute('downloads_updated_at');
        $pivot = $this->getRelationValue('pivot');

        if ($updatedAt === null || ! $pivot instanceof Pivot) {
            return false;
        }

        $updatedAt = $updatedAt instanceof Carbon
            ? $updatedAt
            : Carbon::parse((string) $updatedAt);
        $seenAt = $pivot->getAttribute('downloads_seen_at')
            ?? $pivot->getAttribute('created_at');

        if ($seenAt === null) {
            return true;
        }

        return $updatedAt->greaterThan(
            $seenAt instanceof Carbon
                ? $seenAt
                : Carbon::parse($seenAt),
        );
    }

    /** @param Builder<Game> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', GameStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
