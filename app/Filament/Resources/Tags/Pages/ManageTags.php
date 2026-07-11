<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use App\Support\TagImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ManageRecords;

class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importTags')
                ->label('Import tags')
                ->schema([
                    Textarea::make('names')
                        ->label('Tag names')
                        ->helperText('Separate tags with commas or new lines.')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(TagImporter::class)->import($data['names']);
                })
                ->successNotificationTitle('Tags imported'),
            CreateAction::make(),
        ];
    }
}
