<?php

namespace App\Actions\Games;

use App\Filament\Resources\Games\Schemas\GameForm;
use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Support\DescriptionMediaImporter;
use App\Support\Media;
use App\Support\MediaThumbnail;
use App\Support\RemoteMediaDownloader;
use App\Support\TagImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublishGame
{
    public function __construct(
        private TagImporter $tagImporter,
        private RemoteMediaDownloader $mediaDownloader,
        private DescriptionMediaImporter $descriptionMediaImporter,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Game
    {
        /** @var list<string> $uploadedPaths */
        $uploadedPaths = [];

        try {
            $categoryId = null;

            if (filled($data['category'] ?? null)) {
                $categoryId = $this->resolveCategory((string) $data['category'])->id;
            }

            $status = GameStatus::from((string) ($data['status'] ?? GameStatus::Published->value));
            $slug = filled($data['slug'] ?? null)
                ? (string) $data['slug']
                : $this->uniqueSlug(GameForm::slugFromTitle($data['title'] ?? null));

            $coverPath = $this->mediaDownloader->download(
                (string) $data['cover_url'],
                'games/covers',
            );
            $uploadedPaths[] = $coverPath;

            /** @var list<string> $screenshotPaths */
            $screenshotPaths = [];

            foreach (array_values($data['screenshots'] ?? []) as $screenshotUrl) {
                $path = $this->mediaDownloader->download((string) $screenshotUrl, 'games/screenshots');
                $uploadedPaths[] = $path;
                $screenshotPaths[] = $path;
            }

            $descriptionImport = $this->descriptionMediaImporter->import(
                isset($data['description']) ? (string) $data['description'] : null,
                'description',
            );
            $uploadedPaths = [...$uploadedPaths, ...$descriptionImport['paths']];
            $description = $descriptionImport['html'];

            /** @var list<array<string, mixed>> $releases */
            $releases = [];

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

            return DB::transaction(function () use (
                $data,
                $categoryId,
                $status,
                $slug,
                $coverPath,
                $screenshotPaths,
                $description,
                $releases,
            ): Game {
                $game = Game::query()->create([
                    'category_id' => $categoryId,
                    'title' => $data['title'],
                    'subtitle' => $data['subtitle'] ?? null,
                    'slug' => $slug,
                    'description' => $description,
                    'developer' => $data['developer'] ?? null,
                    'source_name' => $data['source_name'] ?? null,
                    'source_id' => $data['source_id'] ?? null,
                    'source_url' => $data['source_url'] ?? null,
                    'cover_path' => $coverPath,
                    'cover_url' => '',
                    'release_date' => $data['release_date'] ?? null,
                    'status' => $status,
                    'published_at' => $status === GameStatus::Draft
                        ? ($data['published_at'] ?? null)
                        : ($data['published_at'] ?? now()),
                    'views_count' => 0,
                    'downloads_count' => 0,
                ]);

                if (! empty($data['tags']) && is_array($data['tags'])) {
                    /** @var list<string> $tags */
                    $tags = array_values(array_map(strval(...), $data['tags']));
                    $game->tags()->sync($this->tagImporter->importNames($tags));
                }

                foreach ($screenshotPaths as $sortOrder => $path) {
                    $game->screenshots()->create([
                        'path' => $path,
                        'sort_order' => $sortOrder,
                    ]);
                }

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

                $game->forceFill(['downloads_updated_at' => null])->saveQuietly();

                return $game->fresh([
                    'category',
                    'tags',
                    'screenshots',
                    'releases.platforms',
                    'releases.languages',
                    'releases.downloadLinks',
                ]) ?? $game;
            });
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                Media::delete($path);
                MediaThumbnail::deleteFor($path);
            }

            throw $exception;
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

    protected function resolveLanguage(string $value): Language
    {
        $language = Language::query()
            ->where(function ($query) use ($value): void {
                $query->where('code', $value)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($value)]);
            })
            ->first();

        if ($language === null) {
            throw ValidationException::withMessages([
                'releases' => "Unknown language [{$value}].",
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
}
