<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\MediaDeletionService;
use App\Support\MediaThumbnail;

class DeleteGameMedia
{
    public function __construct(private readonly MediaDeletionService $deletionService) {}

    public function __invoke(Game $game): void
    {
        $this->deletePaths($game, $this->pathsFor($game));
    }

    /**
     * @return list<string>
     */
    public function pathsFor(Game $game): array
    {
        $releaseDescriptions = $game->releases()
            ->whereNotNull('description')
            ->pluck('description')
            ->all();
        $coverPath = is_string($game->cover_path) ? $game->cover_path : null;
        $thumbnailPath = $coverPath !== '' && MediaThumbnail::isManagedPath($coverPath)
            ? MediaThumbnail::pathFor((string) $coverPath)
            : null;

        $paths = collect([
            $coverPath,
            $thumbnailPath,
            ...$game->screenshots()->pluck('path')->all(),
            ...$this->pathsFromDescription((string) $game->description),
            ...collect($releaseDescriptions)
                ->flatMap(fn (mixed $description): array => $this->pathsFromDescription((string) $description))
                ->all(),
        ])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        return array_values($paths->unique()->all());
    }

    /**
     * @param  list<string>  $paths
     */
    public function deletePaths(Game $game, array $paths): void
    {
        $this->deletionService->deleteMany($paths);
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

        return $paths;
    }
}
