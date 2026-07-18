<?php

namespace App\Filament\Resources\Docs\Pages;

use App\Filament\Resources\Docs\DocResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateDoc extends CreateRecord
{
    protected static string $resource = DocResource::class;

    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;
}
