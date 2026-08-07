<?php

namespace App\Actions\Media;

use App\Models\Game;
use App\Support\Media;
use App\Support\MediaThumbnail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateCoverThumbnails
{
    /**
     * @return array{generated: int, skipped: int, failed: int}
     */
    public function __invoke(bool $force = false): array
    {
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        Game::query()
            ->whereNotNull('cover_path')
            ->where('cover_path', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($games) use ($force, &$generated, &$skipped, &$failed): void {
                foreach ($games as $game) {
                    $path = (string) $game->cover_path;

                    if (! MediaThumbnail::isManagedPath($path)) {
                        $skipped++;

                        continue;
                    }

                    if (! Media::disk()->exists($path)) {
                        $failed++;

                        continue;
                    }

                    $thumbnailPath = MediaThumbnail::pathFor($path);
                    $activeDisk = Media::diskName();
                    $thumbnailReady = $activeDisk === 'r2'
                        ? Storage::disk('r2')->exists($thumbnailPath)
                            && Storage::disk('public')->exists($thumbnailPath)
                        : Media::disk()->exists($thumbnailPath);

                    if (! $force && $thumbnailReady) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $result = MediaThumbnail::ensureReady($path, $force);
                    } catch (Throwable) {
                        $result = null;
                    }

                    if ($result === null) {
                        $failed++;

                        continue;
                    }

                    $generated++;
                }
            });

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
