<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Games\Schemas\GameForm;
use App\Models\Game;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateGame extends CreateRecord
{
    protected static string $resource = GameResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** @var array<int, string> */
    private array $screenshotPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->screenshotPaths = array_values($data['screenshot_uploads'] ?? []);
        unset($data['screenshot_uploads']);

        if (blank($data['slug'] ?? null)) {
            $data['slug'] = GameForm::slugFromTitle($data['title'] ?? null);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Game $game */
        $game = $this->record;

        foreach ($this->screenshotPaths as $sortOrder => $path) {
            $game->screenshots()->create([
                'path' => $path,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
