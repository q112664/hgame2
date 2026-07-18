<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameScreenshot;
use App\Support\Media;
use App\Support\MediaThumbnail;
use Illuminate\Support\Facades\Log;

class DeleteGameMedia
{
    public function __invoke(Game $game): void
    {
        $this->deletePaths($game, $this->pathsFor($game));
    }

    /**
     * @return list<string>
     */
    public function pathsFor(Game $game): array
    {
        $paths = collect([
            $game->cover_path,
            ...$game->screenshots()->pluck('path')->all(),
            ...$this->pathsFromDescription((string) $game->description),
        ])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        if (is_string($game->cover_path) && $game->cover_path !== '' && MediaThumbnail::isManagedPath($game->cover_path)) {
            $paths->push(MediaThumbnail::pathFor($game->cover_path));
        }

        return array_values($paths->unique()->all());
    }

    /**
     * @param  list<string>  $paths
     */
    public function deletePaths(Game $game, array $paths): void
    {
        foreach ($paths as $path) {
            if ($this->isReferencedElsewhere($game, $path)) {
                continue;
            }

            if (! Media::delete($path)) {
                Log::warning('Game media cleanup left residual objects.', [
                    'game_id' => $game->id,
                    'path' => $path,
                ]);
            }
        }
    }

    private function isReferencedElsewhere(Game $game, string $path): bool
    {
        if (Game::query()
            ->whereKeyNot($game->getKey())
            ->where('cover_path', $path)
            ->exists()) {
            return true;
        }

        if (GameScreenshot::query()
            ->where('path', $path)
            ->where('game_id', '!=', $game->getKey())
            ->exists()) {
            return true;
        }

        return Game::query()
            ->whereKeyNot($game->getKey())
            ->whereNotNull('description')
            ->where('description', 'like', '%'.$path.'%')
            ->exists();
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
