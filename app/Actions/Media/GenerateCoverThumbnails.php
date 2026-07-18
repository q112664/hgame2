<?php

namespace App\Actions\Media;

use App\Models\Game;
use App\Support\Media;
use App\Support\MediaThumbnail;

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

                    if (! MediaThumbnail::isManagedPath($path) || ! Media::disk()->exists($path)) {
                        $skipped++;

                        continue;
                    }

                    $thumbnailPath = MediaThumbnail::pathFor($path);

                    if (! $force && Media::disk()->exists($thumbnailPath)) {
                        $skipped++;

                        continue;
                    }

                    if ($force && Media::disk()->exists($thumbnailPath)) {
                        Media::delete($thumbnailPath);
                    }

                    $result = MediaThumbnail::generate($path);

                    if ($result === null) {
                        // Source already within the card width, or unreadable — both are non-fatal.
                        if (Media::disk()->exists($path)) {
                            $skipped++;
                        } else {
                            $failed++;
                        }

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
