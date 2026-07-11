<?php

namespace App\Filament\Resources\Games\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

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
                TextInput::make('file_size_bytes')
                    ->label('File size')
                    ->numeric()
                    ->minValue(0),
                RichEditor::make('description')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('games/content')
                    ->fileAttachmentsVisibility('public')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Repeater::make('downloadLinks')
                    ->relationship()
                    ->schema([
                        TextInput::make('label')->required()->maxLength(255),
                        TextInput::make('url')->required()->maxLength(2048),
                        Toggle::make('is_active')->default(true)->required(),
                    ])
                    ->columns(2)
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->itemLabel(fn (array $state): string => $state['label'] ?? 'New download link')
                    ->columnSpanFull(),
                Hidden::make('published_at')->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
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
                TextColumn::make('file_size_bytes')
                    ->label('File size')
                    ->formatStateUsing(fn (?int $state): string => $state ? Number::fileSize($state) : '—')
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
