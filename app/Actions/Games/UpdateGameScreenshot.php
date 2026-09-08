<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameScreenshot;
use App\Support\GameApiPayload;
use App\Support\MediaDeletionService;
use App\Support\RemoteMediaDownloader;
use Throwable;

class UpdateGameScreenshot
{
    public function __construct(
        private RemoteMediaDownloader $mediaDownloader,
        private MediaDeletionService $mediaDeletionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Game $game, GameScreenshot $screenshot, array $data): Game
    {
        abort_unless($screenshot->game_id === $game->id, 404);

        $uploadedPaths = [];
        $obsoletePath = null;

        try {
            if (array_key_exists('url', $data)) {
                $path = $this->mediaDownloader->download((string) $data['url'], 'games/screenshots');
                $uploadedPaths[] = $path;

                if (filled($screenshot->path) && $screenshot->path !== $path) {
                    $obsoletePath = (string) $screenshot->path;
                }

                $screenshot->update([
                    'path' => $path,
                    'url' => null,
                ]);
            }

            if (array_key_exists('sort_order', $data)) {
                $this->move($game, $screenshot->fresh() ?? $screenshot, (int) $data['sort_order']);
            }

            if (is_string($obsoletePath)) {
                $this->mediaDeletionService->deleteIfUnreferenced($obsoletePath);
            }

            return GameApiPayload::reload($game);
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                $this->mediaDeletionService->deleteIfUnreferenced($path);
            }

            throw $exception;
        }
    }

    private function move(Game $game, GameScreenshot $screenshot, int $sortOrder): void
    {
        $ordered = $game->screenshots()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $ordered->search(
            fn (GameScreenshot $item): bool => $item->id === $screenshot->id,
        );

        if ($index === false) {
            abort(404);
        }

        $ordered->splice((int) $index, 1);
        $to = min(max(0, $sortOrder), $ordered->count());
        $ordered->splice($to, 0, [$screenshot]);

        foreach ($ordered->values() as $position => $item) {
            /** @var GameScreenshot $item */
            if ((int) $item->sort_order !== $position) {
                $item->update(['sort_order' => $position]);
            }
        }
    }
}
