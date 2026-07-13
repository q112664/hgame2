<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Filament\Resources\Games\Schemas\GameForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReleasesRelationManager extends RelationManager
{
    protected static string $relationship = 'releases';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('platforms')
                    ->relationship('platforms', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('languages')
                    ->relationship('languages', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('version')->maxLength(255),
                TextInput::make('file_size')
                    ->label('File size')
                    ->maxLength(255)
                    ->placeholder('12GB'),
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Repeater::make('downloadLinks')
                    ->relationship()
                    ->simple(
                        TextInput::make('url')
                            ->label('Download URL')
                            ->required()
                            ->maxLength(2048)
                            ->url(),
                    )
                    ->orderColumn('sort_order')
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        return GameForm::normalizeDownloadLink($data);
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                        return GameForm::normalizeDownloadLink($data);
                    })
                    ->columnSpanFull(),
                Hidden::make('published_at')->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('platforms.name')
                    ->label('Platforms')
                    ->badge()
                    ->separator(','),
                TextColumn::make('languages.name')
                    ->label('Languages')
                    ->badge()
                    ->separator(','),
                TextColumn::make('version')
                    ->searchable(),
                TextColumn::make('file_size')
                    ->label('File size')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('download_links_count')
                    ->counts('downloadLinks')
                    ->label('Links'),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
