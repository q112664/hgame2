<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameDetailTranslation;
use App\Support\DescriptionHtmlPaths;
use App\Support\GameApiPayload;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeleteGameDetailVersion
{
    use ResolvesPublishApiTaxonomy;

    public function __construct(private DeleteGameMedia $deleteGameMedia) {}

    public function __invoke(Game $game, string $languageValue): Game
    {
        $language = $this->resolveLanguage($languageValue, 'language');
        $translation = $game->detailTranslations()
            ->where('language_id', $language->id)
            ->first();

        if ($translation === null) {
            throw (new ModelNotFoundException)->setModel(GameDetailTranslation::class);
        }

        $obsoletePaths = DescriptionHtmlPaths::extract((string) ($translation->description ?? ''));
        $translation->delete();

        if ($obsoletePaths !== []) {
            $this->deleteGameMedia->deletePaths($game, $obsoletePaths);
        }

        return GameApiPayload::reload($game);
    }
}
