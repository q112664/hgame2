<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\Tag;
use App\Support\GameApiPayload;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class DetachGameTag
{
    public function __invoke(Game $game, string $tag): Game
    {
        $resolved = Tag::query()
            ->where(function ($query) use ($tag): void {
                $query->where('slug', $tag)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($tag)]);
            })
            ->first();

        if ($resolved === null) {
            throw (new ModelNotFoundException)->setModel(Tag::class, [$tag]);
        }

        $game->tags()->detach($resolved->id);

        return GameApiPayload::reload($game);
    }
}
