<?php

namespace App\Actions\Seo;

use App\Models\Game;
use App\Support\IndexNow;

class SubmitGameToIndexNow
{
    public function __invoke(Game $game): void
    {
        if (! $game->isListedOnSite()) {
            return;
        }

        IndexNow::submitGame($game);
    }
}
