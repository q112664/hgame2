<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameRelease;
use App\Support\DescriptionHtmlPaths;
use App\Support\GameApiPayload;

class DeleteGameRelease
{
    public function __construct(private DeleteGameMedia $deleteGameMedia) {}

    public function __invoke(Game $game, GameRelease $release): Game
    {
        abort_unless($release->game_id === $game->id, 404);

        $obsoletePaths = DescriptionHtmlPaths::extract((string) ($release->description ?? ''));
        $release->delete();

        if ($obsoletePaths !== []) {
            $this->deleteGameMedia->deletePaths($game, $obsoletePaths);
        }

        return GameApiPayload::reload($game);
    }
}
