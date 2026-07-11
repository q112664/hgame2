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

#[Fillable([
    'category_id', 'title', 'slug', 'description', 'developer', 'cover_url', 'cover_path',
    'release_date', 'status', 'published_at', 'views_count', 'downloads_count',
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

    public function scopePublished(Builder $query): void
    {
        $query->where('status', GameStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
