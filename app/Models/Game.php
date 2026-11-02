<?php

namespace App\Models;

use App\Actions\Games\DeleteGameMedia;
use App\Actions\Seo\SubmitGameToIndexNow;
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
 * @property-read EloquentCollection<int, User> $favoritedBy
 * @property-read EloquentCollection<int, User> $likedBy
 */
#[Fillable([
    'category_id', 'title', 'subtitle', 'slug', 'description', 'developer',
    'source_name', 'source_id', 'source_url',
    'cover_url', 'cover_path',
    'release_date', 'status', 'published_at', 'views_count', 'downloads_count', 'likes_count',
    'downloads_updated_at',
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
            if ($game->shouldSubmitIndexNowOnSave()) {
                app(SubmitGameToIndexNow::class)($game);
            }

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
        app(SubmitGameToIndexNow::class)($this);
    }

    public function isListedOnSite(): bool
    {
        if ($this->status !== GameStatus::Published) {
            return false;
        }

        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function shouldSubmitIndexNowOnSave(): bool
    {
        return $this->isListedOnSite() && ! $this->wasListedOnSite();
    }

    private function wasListedOnSite(): bool
    {
        $status = $this->getOriginal('status');
        $status = $status instanceof GameStatus
            ? $status
            : GameStatus::tryFrom((string) $status);

        if ($status !== GameStatus::Published) {
            return false;
        }

        $publishedAt = $this->getOriginal('published_at');

        if ($publishedAt === null || $publishedAt === '') {
            return false;
        }

        $publishedAt = $publishedAt instanceof CarbonInterface
            ? $publishedAt
            : Carbon::parse((string) $publishedAt);

        return $publishedAt->lte(now());
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

    /** Maximum tokens a single query may contribute, so a long paste stays cheap. */
    private const int SearchTokenLimit = 5;

    /** Shortest token that the PostgreSQL trigram fallback can meaningfully index. */
    private const int MinimumTrigramTokenLength = 3;

    /**
     * Match the query against every searchable field, token by token.
     *
     * Words are ANDed and fields are ORed, so "room massage" finds
     * "Room 404: Erotic Massage" even though the phrase never appears verbatim.
     * On PostgreSQL a trigram similarity pass catches typos and partial words;
     * SQLite (local and tests) keeps the substring-only path.
     *
     * `whereLike()` is used instead of plain `like` so matching stays
     * case-insensitive on every driver.
     *
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeMatchingSearch(Builder $query, string $term): Builder
    {
        $tokens = self::searchTokens($term);

        if ($tokens === []) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($tokens): void {
            foreach ($tokens as $token) {
                $builder->where(
                    fn (Builder $tokenQuery): Builder => self::applySearchToken($tokenQuery, $token),
                );
            }
        });
    }

    /**
     * Split a raw query into the words that must each match somewhere.
     *
     * @return list<string>
     */
    private static function searchTokens(string $term): array
    {
        $normalised = trim((string) preg_replace('/\s+/u', ' ', $term));

        if ($normalised === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $normalised) ?: [];

        return array_slice(
            array_values(array_unique(array_filter($tokens, fn (string $token): bool => $token !== ''))),
            0,
            self::SearchTokenLimit,
        );
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    private static function applySearchToken(Builder $query, string $token): Builder
    {
        $like = '%'.addcslashes($token, '%_\\').'%';

        $query
            ->whereLike('title', $like)
            ->orWhereLike('subtitle', $like)
            ->orWhereLike('developer', $like)
            ->orWhereLike('source_name', $like)
            ->orWhereLike('slug', $like);

        // Taxonomy tables are tiny, so they are resolved into ids first: a plain
        // `orWhereHas` would run a correlated subquery for every catalog row and
        // turned a two-word search into a ~600ms query.
        $categoryIds = Category::query()
            ->where(fn (Builder $category): Builder => $category
                ->whereLike('name', $like)
                ->orWhereLike('slug', $like))
            ->pluck('id');

        if ($categoryIds->isNotEmpty()) {
            $query->orWhereIn('category_id', $categoryIds);
        }

        $tagIds = Tag::query()
            ->where(fn (Builder $tags): Builder => $tags
                ->whereLike('name', $like)
                ->orWhereLike('slug', $like))
            ->pluck('id');

        if ($tagIds->isNotEmpty()) {
            $query->orWhereHas(
                'tags',
                fn (Builder $tags): Builder => $tags->whereIn('tags.id', $tagIds),
            );
        }

        $platformIds = Platform::query()
            ->where(fn (Builder $platforms): Builder => $platforms
                ->whereLike('name', $like)
                ->orWhereLike('slug', $like))
            ->pluck('id');

        if ($platformIds->isNotEmpty()) {
            $query->orWhereHas(
                'releases.platforms',
                fn (Builder $platforms): Builder => $platforms->whereIn('platforms.id', $platformIds),
            );
        }

        $languageIds = Language::query()
            ->where(fn (Builder $languages): Builder => $languages
                ->whereLike('name', $like)
                ->orWhereLike('code', $like))
            ->pluck('id');

        if ($languageIds->isNotEmpty()) {
            $query->orWhereHas(
                'releases.languages',
                fn (Builder $languages): Builder => $languages->whereIn('languages.id', $languageIds),
            );
        }

        $releaseGameIds = GameRelease::query()
            ->whereLike('title', $like)
            ->distinct()
            ->limit(500)
            ->pluck('game_id');

        if ($releaseGameIds->isNotEmpty()) {
            $query->orWhereIn('id', $releaseGameIds);
        }

        if (mb_strlen($token) < self::MinimumTrigramTokenLength) {
            return $query;
        }

        if ($query->getConnection()->getDriverName() !== 'pgsql') {
            return $query;
        }

        // `<%` compares the token against the best matching word of the column,
        // which is what a search box needs (`errotic` still finds `Erotic`) and
        // is backed by the gin_trgm_ops indexes.
        $query
            ->orWhereRaw('? <% title', [$token])
            ->orWhereRaw('? <% subtitle', [$token])
            ->orWhereRaw('? <% developer', [$token])
            ->orWhereRaw('? <% source_name', [$token]);

        return $query;
    }
}
