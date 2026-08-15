<?php

namespace App\Filament\Resources\ResourceSources\Pages;

use App\Filament\Resources\ResourceSources\ResourceSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResourceSources extends ManageRecords
{
    protected static string $resource = ResourceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
