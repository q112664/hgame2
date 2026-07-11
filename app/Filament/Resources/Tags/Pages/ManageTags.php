<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use App\Models\Tag;
use App\Support\TagImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

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
                        ->helperText('Separate tags with spaces, commas, or new lines.')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(TagImporter::class)->import($data['names']);
                })
                ->successNotificationTitle('Tags imported'),
            CreateAction::make()
                ->schema([
                    Textarea::make('names')
                        ->label('Tag names')
                        ->helperText('Separate tags with spaces, commas, or new lines.')
                        ->rows(6)
                        ->required(),
                ])
                ->using(function (array $data): Tag {
                    $ids = app(TagImporter::class)->import($data['names']);

                    if ($ids === []) {
                        throw ValidationException::withMessages([
                            'names' => 'Enter at least one tag name.',
                        ]);
                    }

                    return Tag::query()->findOrFail($ids[0]);
                }),
        ];
    }
}
