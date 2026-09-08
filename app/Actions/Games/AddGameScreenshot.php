<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameScreenshot;
use App\Support\GameApiPayload;
use App\Support\MediaDeletionService;
use App\Support\RemoteMediaDownloader;
use Illuminate\Validation\ValidationException;
use Throwable;

class AddGameScreenshot
{
    public function __construct(
        private RemoteMediaDownloader $mediaDownloader,
        private MediaDeletionService $mediaDeletionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Game $game, array $data): Game
    {
        if ($game->screenshots()->count() >= GameScreenshot::MaxPerGame) {
            throw ValidationException::withMessages([
                'url' => 'A game may have at most '.GameScreenshot::MaxPerGame.' screenshots.',
            ]);
        }

        $uploadedPaths = [];

        try {
            $path = $this->mediaDownloader->download((string) $data['url'], 'games/screenshots');
            $uploadedPaths[] = $path;

            $max = $game->screenshots()->max('sort_order');

            $game->screenshots()->create([
                'path' => $path,
                'url' => null,
                'sort_order' => $max === null ? 0 : (int) $max + 1,
            ]);

            return GameApiPayload::reload($game);
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                $this->mediaDeletionService->deleteIfUnreferenced($path);
            }

            throw $exception;
        }
    }
}
