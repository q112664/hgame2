<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameScreenshot;
use App\Support\Media;
use Illuminate\Support\Facades\DB;

class SyncGameScreenshots
{
    /**
     * @param  array<int, mixed>  $paths
     */
    public function __invoke(Game $game, array $paths): void
    {
        $paths = collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();

        $existingByPath = $game->screenshots()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->get()
            ->keyBy('path');

        $keptIds = [];

        foreach ($paths as $sortOrder => $path) {
            $screenshot = $existingByPath->get($path);

            if ($screenshot instanceof GameScreenshot) {
                $screenshot->update(['sort_order' => $sortOrder]);
            } else {
                $screenshot = $game->screenshots()->create([
                    'path' => $path,
                    'sort_order' => $sortOrder,
                ]);
            }

            $keptIds[] = $screenshot->id;
        }

        $removedPaths = $game->screenshots()
            ->whereKeyNot($keptIds)
            ->get()
            ->map(fn (GameScreenshot $screenshot): ?string => filled($screenshot->path) ? $screenshot->path : null)
            ->filter()
            ->values()
            ->all();

        $game->screenshots()
            ->whereKeyNot($keptIds)
            ->get()
            ->each(function (GameScreenshot $screenshot): void {
                $screenshot->delete();
            });

        DB::afterCommit(function () use ($removedPaths): void {
            foreach ($removedPaths as $path) {
                Media::delete($path);
            }
        });
    }
}
