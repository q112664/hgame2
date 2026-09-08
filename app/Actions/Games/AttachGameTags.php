<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Support\GameApiPayload;
use App\Support\TagImporter;

class AttachGameTags
{
    public function __construct(private TagImporter $tagImporter) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Game $game, array $data): Game
    {
        /** @var list<string> $names */
        $names = array_values(array_map(strval(...), is_array($data['tags'] ?? null) ? $data['tags'] : []));

        $game->tags()->syncWithoutDetaching($this->tagImporter->importNames($names));

        return GameApiPayload::reload($game);
    }
}
