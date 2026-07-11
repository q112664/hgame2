<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Filament\Forms\Components\ScreenshotsFileUpload;
use App\Models\Game;
use App\Models\GameScreenshot;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ScreenshotsRelationManager extends RelationManager
{
    protected static string $relationship = 'screenshots';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('games/screenshots')
                    ->visibility('public')
                    ->required(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('alt')
                    ->helperText('Leave blank to use the game title on the frontend.'),
                Section::make('Advanced')
                    ->schema([
                        TextInput::make('url')->label('External image URL')->maxLength(2048),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                Stack::make([
                    ImageColumn::make('preview')
                        ->state(fn (GameScreenshot $record): ?string => $record->path
                            ? Storage::disk('public')->url($record->path)
                            : $record->url)
                        ->imageWidth('100%')
                        ->imageHeight(72)
                        ->extraAttributes([
                            'class' => 'min-w-0 overflow-hidden p-0',
                        ])
                        ->extraImgAttributes([
                            'class' => 'max-w-full! rounded-md object-cover',
                        ]),
                    TextColumn::make('alt')
                        ->placeholder('—')
                        ->limit(24)
                        ->searchable()
                        ->color('gray')
                        ->size(TextSize::ExtraSmall),
                ]),
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Upload screenshots')
                    ->modalHeading('Upload screenshots')
                    ->createAnother(false)
                    ->schema([
                        ScreenshotsFileUpload::make('paths')
                            ->required(),
                        TextInput::make('alt')
                            ->helperText('Optional. Applied to all uploaded screenshots in this batch.'),
                    ])
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var Game $game */
                        $game = $livewire->getOwnerRecord();
                        $paths = array_values($data['paths'] ?? []);
                        $sortOrder = ((int) $game->screenshots()->max('sort_order')) + 1;
                        $alt = $data['alt'] ?? null;
                        $first = null;

                        foreach ($paths as $path) {
                            $screenshot = $game->screenshots()->create([
                                'path' => $path,
                                'alt' => $alt,
                                'sort_order' => $sortOrder++,
                            ]);

                            $first ??= $screenshot;
                        }

                        return $first ?? $game->screenshots()->make();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order');
    }
}
