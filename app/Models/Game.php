<?php

namespace App\Models;

use App\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'category_id', 'title', 'subtitle', 'slug', 'description', 'developer', 'cover_url', 'cover_path',
    'release_date', 'status', 'published_at', 'views_count', 'downloads_count', 'downloads_updated_at',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(GameRelease::class)->orderBy('sort_order');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(GameScreenshot::class)->orderBy('sort_order');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withPivot('downloads_seen_at')
            ->withTimestamps();
    }

    public function touchDownloadsUpdatedAt(): void
    {
        $this->forceFill([
            'downloads_updated_at' => now(),
        ])->saveQuietly();
    }

    public function hasUnreadDownloadUpdate(): bool
    {
        if ($this->downloads_updated_at === null || $this->pivot === null) {
            return false;
        }

        $seenAt = $this->pivot->downloads_seen_at ?? $this->pivot->created_at;

        if ($seenAt === null) {
            return true;
        }

        return $this->downloads_updated_at->greaterThan(
            $seenAt instanceof Carbon
                ? $seenAt
                : Carbon::parse($seenAt),
        );
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', GameStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
