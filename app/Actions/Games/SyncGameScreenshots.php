<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameScreenshot;
use Illuminate\Support\Facades\Storage;

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

        $game->screenshots()
            ->whereKeyNot($keptIds)
            ->get()
            ->each(function (GameScreenshot $screenshot): void {
                if (filled($screenshot->path)) {
                    Storage::disk('public')->delete($screenshot->path);
                }

                $screenshot->delete();
            });
    }
}
