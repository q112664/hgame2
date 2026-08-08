<?php

namespace App\Support;

use App\Models\Game;
use Illuminate\Support\Str;

final class MediaDeletionService
{
    public function __construct(
        private readonly MediaPathCollector $pathCollector,
        private readonly MediaOperationCoordinator $coordinator,
    ) {}

    public function deleteIfUnreferenced(?string $path): bool
    {
        if (blank($path) || Str::startsWith((string) $path, ['http://', 'https://', '/'])) {
            return true;
        }

        return $this->coordinator->cutover(function () use ($path): bool {
            if ($this->isReferenced((string) $path)) {
                return true;
            }

            return Media::delete($path);
        });
    }

    /** @param list<string> $paths */
    public function deleteMany(array $paths): bool
    {
        $success = true;

        foreach (array_values(array_unique($paths)) as $path) {
            $success = $this->deleteIfUnreferenced($path) && $success;
        }

        return $success;
    }

    private function isReferenced(string $path): bool
    {
        if ($this->pathCollector->isReferenced($path)) {
            return true;
        }

        if (! str_contains($path, '/thumbs/') && ! str_starts_with($path, 'thumbs/')) {
            return false;
        }

        return Game::query()
            ->whereNotNull('cover_path')
            ->pluck('cover_path')
            ->contains(fn (mixed $coverPath): bool => is_string($coverPath)
                && MediaThumbnail::pathFor($coverPath) === $path);
    }
}
