<?php

namespace App\Models;

use App\Actions\Games\DeleteGameMedia;
use App\GameStatus;
use App\Jobs\GenerateCoverThumbnail;
use App\Notifications\FavoriteDownloadsUpdatedNotification;
use App\Support\MediaThumbnail;
use Carbon\CarbonInterface;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * @property GameStatus $status
 * @property CarbonInterface|null $release_date
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $downloads_updated_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Category|null $category
 * @property-read EloquentCollection<int, Tag> $tags
 * @property-read EloquentCollection<int, GameDetailTranslation> $detailTranslations
 * @property-read EloquentCollection<int, GameRelease> $releases
 * @property-read EloquentCollection<int, GameScreenshot> $screenshots
 * @property-read EloquentCollection<int, GameComment> $comments
 * @property-read EloquentCollection<int, User> $favoritedBy
 * @property-read EloquentCollection<int, User> $likedBy
 */
#[Fillable([
    'category_id', 'title', 'subtitle', 'slug', 'description', 'developer',
    'source_name', 'source_id', 'source_url',
    'cover_url', 'cover_path',
    'release_date', 'status', 'published_at', 'views_count', 'downloads_count', 'likes_count',
    'ratings_count', 'ratings_avg', 'downloads_updated_at',
])]
class Game extends Model
{
    /** @var list<string> */
    public array $mediaPathsForDeletion = [];

    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (Game $game): void {
            if (blank($game->cover_path)) {
                return;
            }

            // wasChanged() is empty on insert; wasRecentlyCreated covers first save.
            if (! $game->wasRecentlyCreated && ! $game->wasChanged('cover_path')) {
                return;
            }

            $coverPath = (string) $game->cover_path;

            if (! MediaThumbnail::isManagedPath($coverPath)) {
                return;
            }

            if (MediaThumbnail::generate($coverPath) === null && ! app()->environment('testing')) {
                GenerateCoverThumbnail::dispatch((int) $game->getKey(), $coverPath)->afterCommit();
            }
        });

        static::deleting(function (Game $game): void {
            $game->mediaPathsForDeletion = app(DeleteGameMedia::class)->pathsFor($game);
        });

        static::deleted(function (Game $game): void {
            $paths = $game->mediaPathsForDeletion;

            DB::afterCommit(function () use ($game, $paths): void {
                app(DeleteGameMedia::class)->deletePaths($game, $paths);
            });
        });
    }

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'status' => GameStatus::class,
            'published_at' => 'datetime',
            'downloads_updated_at' => 'datetime',
            'ratings_count' => 'integer',
            'ratings_avg' => 'float',
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

    /** @return HasMany<GameDetailTranslation, $this> */
    public function detailTranslations(): HasMany
    {
        return $this->hasMany(GameDetailTranslation::class)
            ->orderBy('sort_order')
            ->orderBy('id');
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

    /** @return BelongsToMany<User, $this> */
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')
            ->withTimestamps();
    }

    /** @return HasMany<GameComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(GameComment::class);
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeWithCardData(Builder $query): Builder
    {
        return $query->with([
            'category:id,name,slug',
            'tags:id,name,slug',
            'releases' => fn ($releases) => $releases->withCardSummary(),
        ]);
    }

    /**
     * When this resource was listed on the site (catalog “latest”, SEO datePublished).
     */
    public function sitePublishedAt(): ?CarbonInterface
    {
        return $this->published_at;
    }

    /**
     * User-facing content change time for downloads (SEO dateModified, “updated” sort).
     *
     * Uses downloads_updated_at only when set — never Eloquent updated_at — so
     * views/admin metadata saves do not look like resource updates.
     * Falls back to site publish time when downloads have never been updated.
     */
    public function contentModifiedAt(): ?CarbonInterface
    {
        return $this->downloads_updated_at ?? $this->published_at;
    }

    public function touchDownloadsUpdatedAt(): void
    {
        $this->forceFill([
            'downloads_updated_at' => now(),
        ])->saveQuietly();

        $this->notifyFavoritersOfDownloadUpdate();
    }

    /**
     * Push a coalesced "downloads updated" notification to every user who favorited this game.
     */
    public function notifyFavoritersOfDownloadUpdate(): void
    {
        $favoriters = $this->favoritedBy()->get();

        if ($favoriters->isEmpty()) {
            return;
        }

        $gameId = $this->id;

        foreach ($favoriters as $user) {
            $user->notifications()
                ->where('type', 'favorite.downloads_updated')
                ->whereNull('read_at')
                ->where('data->game_id', $gameId)
                ->delete();
        }

        Notification::send(
            $favoriters,
            new FavoriteDownloadsUpdatedNotification($this),
        );
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

    /**
     * Match title, subtitle, developer, category, tags, platforms, or languages.
     *
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeMatchingSearch(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('title', 'like', $like)
                ->orWhere('subtitle', 'like', $like)
                ->orWhere('developer', 'like', $like)
                ->orWhereHas(
                    'category',
                    fn (Builder $category): Builder => $category
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like),
                )
                ->orWhereHas(
                    'tags',
                    fn (Builder $tags): Builder => $tags->where('name', 'like', $like),
                )
                ->orWhereHas(
                    'releases.platforms',
                    fn (Builder $platforms): Builder => $platforms
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like),
                )
                ->orWhereHas(
                    'releases.languages',
                    fn (Builder $languages): Builder => $languages
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like),
                );
        });
    }
}
