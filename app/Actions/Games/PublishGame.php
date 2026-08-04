<?php

namespace App\Actions\Games;

use App\Models\Game;

/**
 * @deprecated Use SaveGameFromApi::create() instead.
 */
class PublishGame
{
    public function __construct(private SaveGameFromApi $saveGameFromApi) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Game
    {
        return $this->saveGameFromApi->create($data);
    }
}
