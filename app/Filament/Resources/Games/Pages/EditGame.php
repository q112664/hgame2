<?php

namespace App\Filament\Resources\Games\Pages;

use App\Actions\Games\SyncGameScreenshots;
use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditGame extends EditRecord
{
    protected static string $resource = GameResource::class;

    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;

    /** @var array<int, string> */
    private array $screenshotPaths = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Game $game */
        $game = $this->getRecord();

        $data['screenshot_uploads'] = $game->screenshots()
            ->orderBy('sort_order')
            ->pluck('path')
            ->filter()
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->screenshotPaths = array_values($data['screenshot_uploads'] ?? []);
        unset($data['screenshot_uploads']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Game $game */
        $game = $this->record;

        app(SyncGameScreenshots::class)($game, $this->screenshotPaths);
    }
}
