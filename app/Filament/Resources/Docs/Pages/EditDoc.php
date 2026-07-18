<?php

namespace App\Filament\Resources\Docs\Pages;

use App\Filament\Resources\Docs\DocResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditDoc extends EditRecord
{
    protected static string $resource = DocResource::class;

    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
