<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameScreenshot;
use App\Support\GameApiPayload;
use App\Support\MediaDeletionService;

class DeleteGameScreenshot
{
    public function __construct(private MediaDeletionService $mediaDeletionService) {}

    public function __invoke(Game $game, GameScreenshot $screenshot): Game
    {
        abort_unless($screenshot->game_id === $game->id, 404);

        $path = filled($screenshot->path) ? (string) $screenshot->path : null;
        $screenshot->delete();

        if (is_string($path)) {
            $this->mediaDeletionService->deleteIfUnreferenced($path);
        }

        return GameApiPayload::reload($game);
    }
}
