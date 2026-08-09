<?php

namespace App\Actions\Games;

use App\Filament\Resources\Games\Schemas\GameForm;
use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Support\DescriptionMediaImporter;
use App\Support\MediaDeletionService;
use App\Support\MediaThumbnail;
use App\Support\RemoteMediaDownloader;
use App\Support\TagImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveGameFromApi
{
    public function __construct(
        private TagImporter $tagImporter,
        private RemoteMediaDownloader $mediaDownloader,
        private DescriptionMediaImporter $descriptionMediaImporter,
        private SyncGameScreenshots $syncGameScreenshots,
        private DeleteGameMedia $deleteGameMedia,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Game
    {
        return $this->persist($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Game $game, array $data): Game
    {
        return $this->persist($data, $game);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(array $data, ?Game $existing = null): Game
    {
        $isUpdate = $existing instanceof Game;

        /** @var list<string> $uploadedPaths */
        $uploadedPaths = [];

        /** @var list<string> $obsoletePaths */
        $obsoletePaths = [];

        try {
            $categoryId = $isUpdate ? $existing->category_id : null;

            if (array_key_exists('category', $data)) {
                $categoryId = filled($data['category'] ?? null)
                    ? $this->resolveCategory((string) $data['category'])->id
                    : null;
            }

            $status = $isUpdate
                ? $existing->status
                : GameStatus::from((string) ($data['status'] ?? GameStatus::Published->value));

            if (array_key_exists('status', $data) && filled($data['status'] ?? null)) {
                $status = GameStatus::from((string) $data['status']);
            }

            $title = array_key_exists('title', $data)
                ? (string) $data['title']
                : (string) ($existing instanceof Game ? $existing->title : '');

            $slug = $isUpdate ? (string) $existing->slug : null;

            if (array_key_exists('slug', $data)) {
                $slug = filled($data['slug'] ?? null)
                    ? (string) $data['slug']
                    : ($isUpdate
                        ? (string) $existing->slug
                        : $this->uniqueSlug(GameForm::slugFromTitle($title)));
            } elseif (! $isUpdate) {
                $slug = $this->uniqueSlug(GameForm::slugFromTitle($title));
            }

            $coverPath = $isUpdate ? $existing->cover_path : null;

            if (array_key_exists('cover_url', $data)) {
                $newCoverPath = $this->mediaDownloader->download(
                    (string) $data['cover_url'],
                    'games/covers',
                );
                $uploadedPaths[] = $newCoverPath;

                if ($isUpdate && filled($existing->cover_path) && $existing->cover_path !== $newCoverPath) {
                    $obsoletePaths[] = (string) $existing->cover_path;

                    if (MediaThumbnail::isManagedPath((string) $existing->cover_path)) {
                        $obsoletePaths[] = MediaThumbnail::pathFor((string) $existing->cover_path);
                    }
                }

                $coverPath = $newCoverPath;
            } elseif (! $isUpdate) {
                $coverPath = $this->mediaDownloader->download(
                    (string) $data['cover_url'],
                    'games/covers',
                );
                $uploadedPaths[] = $coverPath;
            }

            /** @var list<string>|null $screenshotPaths */
            $screenshotPaths = null;

            if (array_key_exists('screenshots', $data)) {
                $screenshotPaths = [];

                foreach (array_values($data['screenshots'] ?? []) as $screenshotUrl) {
                    $path = $this->mediaDownloader->download((string) $screenshotUrl, 'games/screenshots');
                    $uploadedPaths[] = $path;
                    $screenshotPaths[] = $path;
                }
            }

            $description = $isUpdate ? $existing->description : null;

            if (array_key_exists('description', $data)) {
                $descriptionImport = $this->descriptionMediaImporter->import(
                    isset($data['description']) ? (string) $data['description'] : null,
                    'description',
                );
                $uploadedPaths = [...$uploadedPaths, ...$descriptionImport['paths']];
                $description = $descriptionImport['html'];

                if ($isUpdate) {
                    $obsoletePaths = [
                        ...$obsoletePaths,
                        ...$this->orphanedDescriptionPaths(
                            (string) ($existing->description ?? ''),
                            (string) ($description ?? ''),
                        ),
                    ];
                }
            }

            /** @var list<array{language_id: int, description: string|null, sort_order: int}>|null $detailTranslations */
            $detailTranslations = null;

            if (array_key_exists('detail_versions', $data)) {
                $detailTranslations = [];
                $seenLanguageIds = [];

                if ($isUpdate) {
                    $existing->loadMissing('detailTranslations');

                    foreach ($existing->detailTranslations as $oldTranslation) {
                        $obsoletePaths = [
                            ...$obsoletePaths,
                            ...$this->pathsFromDescription((string) ($oldTranslation->description ?? '')),
                        ];
                    }
                }

                foreach (array_values($data['detail_versions'] ?? []) as $sortOrder => $translationData) {
                    /** @var array<string, mixed> $translationData */
                    $language = $this->resolveLanguage(
                        trim((string) ($translationData['language'] ?? '')),
                        'detail_versions',
                    );

                    if (in_array($language->id, $seenLanguageIds, true)) {
                        throw ValidationException::withMessages([
                            'detail_versions' => "Language [{$language->name}] may only appear once.",
                        ]);
                    }

                    $seenLanguageIds[] = $language->id;
                    $descriptionImport = $this->descriptionMediaImporter->import(
                        isset($translationData['description'])
                            ? (string) $translationData['description']
                            : null,
                        "detail_versions.{$sortOrder}.description",
                    );
                    $uploadedPaths = [...$uploadedPaths, ...$descriptionImport['paths']];
                    $detailTranslations[] = [
                        'language_id' => $language->id,
                        'description' => $descriptionImport['html'],
                        'sort_order' => isset($translationData['sort_order'])
                            ? (int) $translationData['sort_order']
                            : $sortOrder,
                    ];
                }
            }

            /** @var list<array<string, mixed>>|null $releases */
            $releases = null;

            if (array_key_exists('releases', $data)) {
                $releases = [];

                if ($isUpdate) {
                    $existing->loadMissing('releases');

                    foreach ($existing->releases as $oldRelease) {
                        $obsoletePaths = [
                            ...$obsoletePaths,
                            ...$this->pathsFromDescription((string) ($oldRelease->description ?? '')),
                        ];
                    }
                }

                foreach (array_values($data['releases'] ?? []) as $sortOrder => $releaseData) {
                    /** @var array<string, mixed> $releaseData */
                    $releaseDescriptionImport = $this->descriptionMediaImporter->import(
                        isset($releaseData['description']) ? (string) $releaseData['description'] : null,
                        "releases.{$sortOrder}.description",
                    );
                    $uploadedPaths = [...$uploadedPaths, ...$releaseDescriptionImport['paths']];
                    $releaseData['description'] = $releaseDescriptionImport['html'];
                    $releases[] = $releaseData;
                }
            }

            $game = DB::transaction(function () use (
                $data,
                $existing,
                $isUpdate,
                $categoryId,
                $status,
                $title,
                $slug,
                $coverPath,
                $screenshotPaths,
                $description,
                $detailTranslations,
                $releases,
            ): Game {
                $attributes = [
                    'category_id' => $categoryId,
                    'title' => $title,
                    'slug' => $slug,
                    'cover_path' => $coverPath,
                    'status' => $status,
                ];

                if (! $isUpdate) {
                    $attributes['cover_url'] = '';
                    $attributes['views_count'] = 0;
                    $attributes['downloads_count'] = 0;
                }

                foreach ([
                    'subtitle',
                    'developer',
                    'source_name',
                    'source_id',
                    'source_url',
                    'release_date',
                ] as $field) {
                    if (! $isUpdate || array_key_exists($field, $data)) {
                        $attributes[$field] = $data[$field] ?? null;
                    }
                }

                if (! $isUpdate || array_key_exists('description', $data)) {
                    $attributes['description'] = $description;
                }

                if (! $isUpdate || array_key_exists('status', $data) || array_key_exists('published_at', $data)) {
                    if (array_key_exists('published_at', $data)) {
                        $attributes['published_at'] = $data['published_at'];
                    } elseif (! $isUpdate || array_key_exists('status', $data)) {
                        $attributes['published_at'] = $status === GameStatus::Draft
                            ? ($isUpdate ? $existing->published_at : null)
                            : ($isUpdate ? ($existing->published_at ?? now()) : now());
                    }
                }

                if ($isUpdate) {
                    $existing->fill($attributes)->save();
                    $game = $existing;
                } else {
                    $game = Game::query()->create($attributes);
                }

                if (! $isUpdate || array_key_exists('tags', $data)) {
                    /** @var list<string> $tags */
                    $tags = is_array($data['tags'] ?? null)
                        ? array_values(array_map(strval(...), $data['tags']))
                        : [];
                    $game->tags()->sync($this->tagImporter->importNames($tags));
                }

                if ($screenshotPaths !== null) {
                    ($this->syncGameScreenshots)($game, $screenshotPaths);
                }

                if ($releases !== null) {
                    $game->releases()->get()->each->delete();
                    $this->createReleases($game, $releases);
                }

                if ($detailTranslations !== null) {
                    $game->detailTranslations()->delete();
                    $this->createDetailTranslations($game, $detailTranslations);
                }

                if (! $isUpdate) {
                    $game->forceFill(['downloads_updated_at' => null])->saveQuietly();
                }

                return $game->fresh([
                    'category',
                    'tags',
                    'screenshots',
                    'detailTranslations.language',
                    'releases.platforms',
                    'releases.languages',
                    'releases.downloadLinks',
                ]) ?? $game;
            });

            if ($obsoletePaths !== []) {
                $this->deleteGameMedia->deletePaths($game, array_values(array_unique($obsoletePaths)));
            }

            return $game;
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                app(MediaDeletionService::class)->deleteIfUnreferenced($path);
                MediaThumbnail::deleteFor($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     */
    private function createReleases(Game $game, array $releases): void
    {
        foreach ($releases as $sortOrder => $releaseData) {
            $release = $game->releases()->create([
                'title' => $releaseData['title'],
                'version' => $releaseData['version'] ?? null,
                'file_size' => $releaseData['file_size'] ?? null,
                'description' => $releaseData['description'] ?? null,
                'is_active' => (bool) ($releaseData['is_active'] ?? true),
                'published_at' => $releaseData['published_at'] ?? now(),
                'sort_order' => $sortOrder,
            ]);

            $platformValues = is_array($releaseData['platforms'] ?? null)
                ? array_values($releaseData['platforms'])
                : [];
            $platformIds = array_map(
                fn (mixed $value): int => $this->resolvePlatform((string) $value)->id,
                $platformValues,
            );

            $languageValues = is_array($releaseData['languages'] ?? null)
                ? array_values($releaseData['languages'])
                : [];
            $languageIds = array_map(
                fn (mixed $value): int => $this->resolveLanguage((string) $value)->id,
                $languageValues,
            );

            $release->platforms()->sync($platformIds);
            $release->languages()->sync($languageIds);

            foreach (array_values($releaseData['download_links'] ?? []) as $linkSortOrder => $url) {
                $normalized = GameForm::normalizeDownloadLink([
                    'url' => (string) $url,
                ]);

                $release->downloadLinks()->create([
                    ...$normalized,
                    'sort_order' => $linkSortOrder,
                ]);
            }
        }
    }

    /**
     * @param  list<array{language_id: int, description: string|null, sort_order: int}>  $translations
     */
    private function createDetailTranslations(Game $game, array $translations): void
    {
        foreach ($translations as $translation) {
            $game->detailTranslations()->create($translation);
        }
    }

    protected function resolveCategory(string $value): Category
    {
        $category = Category::query()
            ->where(function ($query) use ($value): void {
                $query->where('slug', $value)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($value)]);
            })
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category' => "Unknown category [{$value}].",
            ]);
        }

        return $category;
    }

    protected function resolvePlatform(string $value): Platform
    {
        $platform = Platform::query()
            ->where(function ($query) use ($value): void {
                $query->where('slug', $value)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($value)]);
            })
            ->first();

        if ($platform === null) {
            throw ValidationException::withMessages([
                'releases' => "Unknown platform [{$value}].",
            ]);
        }

        return $platform;
    }

    protected function resolveLanguage(string $value, string $errorKey = 'releases'): Language
    {
        $language = Language::query()
            ->where(function ($query) use ($value): void {
                $query->where('code', $value)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($value)]);
            })
            ->first();

        if ($language === null) {
            throw ValidationException::withMessages([
                $errorKey => "Unknown language [{$value}].",
            ]);
        }

        return $language;
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (Game::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return list<string>
     */
    private function orphanedDescriptionPaths(string $oldHtml, string $newHtml): array
    {
        $oldPaths = $this->pathsFromDescription($oldHtml);
        $newPaths = $this->pathsFromDescription($newHtml);

        return array_values(array_diff($oldPaths, $newPaths));
    }

    /**
     * @return list<string>
     */
    private function pathsFromDescription(string $description): array
    {
        if ($description === '') {
            return [];
        }

        preg_match_all(
            '#(?:/storage/|https?://[^"\'>\s]+/)(games/(?:covers|screenshots|content)/[^"\'>\s?]+)#i',
            $description,
            $matches,
        );

        $paths = [];

        foreach ($matches[1] as $path) {
            $paths[] = ltrim($path, '/');
        }

        return array_values(array_unique($paths));
    }
}
