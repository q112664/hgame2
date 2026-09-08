<?php

namespace App\Actions\Games;

use App\Models\Game;
use App\Models\GameDetailTranslation;
use App\Support\DescriptionHtmlPaths;
use App\Support\DescriptionMediaImporter;
use App\Support\GameApiPayload;
use App\Support\MediaDeletionService;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpsertGameDetailVersion
{
    use ResolvesPublishApiTaxonomy;

    public function __construct(
        private DescriptionMediaImporter $descriptionMediaImporter,
        private DeleteGameMedia $deleteGameMedia,
        private MediaDeletionService $mediaDeletionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Game $game, string $languageValue, array $data): Game
    {
        $language = $this->resolveLanguage($languageValue, 'language');
        $existing = $game->detailTranslations()
            ->where('language_id', $language->id)
            ->first();

        if (
            $existing === null
            && $game->detailTranslations()->count() >= GameDetailTranslation::MaxPerGame
        ) {
            throw ValidationException::withMessages([
                'language' => 'A game may have at most '.GameDetailTranslation::MaxPerGame.' localized detail versions.',
            ]);
        }

        $uploadedPaths = [];
        $html = $existing?->description;
        $obsoletePaths = [];

        try {
            if (array_key_exists('description', $data) || $existing === null) {
                $import = $this->descriptionMediaImporter->import(
                    isset($data['description']) ? (string) $data['description'] : null,
                    'description',
                );
                $uploadedPaths = $import['paths'];
                $html = $import['html'];

                if ($existing instanceof GameDetailTranslation) {
                    $obsoletePaths = DescriptionHtmlPaths::orphaned(
                        (string) ($existing->description ?? ''),
                        (string) ($html ?? ''),
                    );
                }
            }

            if (array_key_exists('sort_order', $data)) {
                $sortOrder = (int) $data['sort_order'];
            } elseif ($existing instanceof GameDetailTranslation) {
                $sortOrder = (int) $existing->sort_order;
            } else {
                $max = $game->detailTranslations()->max('sort_order');
                $sortOrder = $max === null ? 0 : (int) $max + 1;
            }

            $game->detailTranslations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'description' => $html,
                    'sort_order' => $sortOrder,
                ],
            );

            if ($obsoletePaths !== []) {
                $this->deleteGameMedia->deletePaths($game, $obsoletePaths);
            }

            return GameApiPayload::reload($game);
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                $this->mediaDeletionService->deleteIfUnreferenced($path);
            }

            throw $exception;
        }
    }
}
