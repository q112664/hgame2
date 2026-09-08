<?php

namespace App\Actions\Games;

use App\Filament\Resources\Games\Schemas\GameForm;
use App\Models\Game;
use App\Models\GameRelease;
use App\Support\DescriptionHtmlPaths;
use App\Support\DescriptionMediaImporter;
use App\Support\GameApiPayload;
use App\Support\MediaDeletionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaveGameReleaseFromApi
{
    use ResolvesPublishApiTaxonomy;

    public function __construct(
        private DescriptionMediaImporter $descriptionMediaImporter,
        private DeleteGameMedia $deleteGameMedia,
        private MediaDeletionService $mediaDeletionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Game $game, array $data): Game
    {
        $uploadedPaths = [];

        try {
            if (array_key_exists('description', $data)) {
                $import = $this->descriptionMediaImporter->import(
                    isset($data['description']) ? (string) $data['description'] : null,
                    'description',
                );
                $uploadedPaths = $import['paths'];
                $data['description'] = $import['html'];
            }

            $release = DB::transaction(function () use ($game, $data): GameRelease {
                $max = $game->releases()->max('sort_order');
                $sortOrder = $max === null ? 0 : (int) $max + 1;

                $release = $game->releases()->create([
                    'user_id' => $this->resolveContributorId(
                        isset($data['contributor']) ? (string) $data['contributor'] : null,
                        'contributor',
                    ),
                    'title' => $data['title'],
                    'version' => $data['version'] ?? null,
                    'file_size' => $data['file_size'] ?? null,
                    'description' => $data['description'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'published_at' => $data['published_at'] ?? now(),
                    'sort_order' => $sortOrder,
                ]);

                $this->syncPlatforms($release, array_values($data['platforms'] ?? []));
                $this->syncLanguages($release, array_values($data['languages'] ?? []));
                $this->syncDownloadLinks($release, array_values($data['download_links'] ?? []));

                return $release;
            });

            if (filter_var($data['touch_downloads'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $game->touchDownloadsUpdatedAt();
            }

            return GameApiPayload::reload($game);
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                $this->mediaDeletionService->deleteIfUnreferenced($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Game $game, GameRelease $release, array $data): Game
    {
        abort_unless($release->game_id === $game->id, 404);

        $uploadedPaths = [];
        $obsoletePaths = [];

        try {
            if (array_key_exists('description', $data)) {
                $import = $this->descriptionMediaImporter->import(
                    isset($data['description']) ? (string) $data['description'] : null,
                    'description',
                );
                $uploadedPaths = $import['paths'];
                $data['description'] = $import['html'];
                $obsoletePaths = DescriptionHtmlPaths::orphaned(
                    (string) ($release->description ?? ''),
                    (string) ($data['description'] ?? ''),
                );
            }

            DB::transaction(function () use ($release, $data): void {
                $attributes = [];

                foreach (['title', 'version', 'file_size', 'description', 'published_at'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $attributes[$field] = $data[$field];
                    }
                }

                if (array_key_exists('is_active', $data)) {
                    $attributes['is_active'] = (bool) $data['is_active'];
                }

                if (array_key_exists('contributor', $data)) {
                    $attributes['user_id'] = $this->resolveContributorId(
                        isset($data['contributor']) ? (string) $data['contributor'] : null,
                        'contributor',
                    );
                }

                if ($attributes !== []) {
                    $release->fill($attributes)->save();
                }

                if (array_key_exists('platforms', $data)) {
                    $this->syncPlatforms($release, array_values($data['platforms'] ?? []));
                }

                if (array_key_exists('languages', $data)) {
                    $this->syncLanguages($release, array_values($data['languages'] ?? []));
                }

                if (array_key_exists('download_links', $data)) {
                    $this->syncDownloadLinks($release, array_values($data['download_links'] ?? []));
                }
            });

            if ($obsoletePaths !== []) {
                $this->deleteGameMedia->deletePaths($game, $obsoletePaths);
            }

            if (filter_var($data['touch_downloads'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $game->touchDownloadsUpdatedAt();
            }

            return GameApiPayload::reload($game);
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                $this->mediaDeletionService->deleteIfUnreferenced($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<mixed>  $values
     */
    private function syncPlatforms(GameRelease $release, array $values): void
    {
        $release->platforms()->sync(array_map(
            fn (mixed $value): int => $this->resolvePlatform((string) $value, 'platforms')->id,
            $values,
        ));
    }

    /**
     * @param  list<mixed>  $values
     */
    private function syncLanguages(GameRelease $release, array $values): void
    {
        $release->languages()->sync(array_map(
            fn (mixed $value): int => $this->resolveLanguage((string) $value, 'languages')->id,
            $values,
        ));
    }

    /**
     * @param  list<mixed>  $urls
     */
    private function syncDownloadLinks(GameRelease $release, array $urls): void
    {
        $release->downloadLinks()->get()->each->delete();

        foreach ($urls as $sortOrder => $url) {
            $normalized = GameForm::normalizeDownloadLink([
                'url' => (string) $url,
            ]);

            $release->downloadLinks()->create([
                ...$normalized,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
