<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestResourcesTable extends TableWidget
{
    protected static ?string $heading = 'Latest resources';

    protected int|string|array $columnSpan = 1;

    protected int $defaultPaginationPageOption = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Game::query()
                    ->with('category:id,name')
                    ->latest('updated_at')
                    ->latest('id'),
            )
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(36)
                    ->tooltip(fn (Game $record): string => $record->title),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->url(fn (Game $record): string => GameResource::getUrl('edit', ['record' => $record], panel: 'admin')),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all')
                    ->url(GameResource::getUrl(panel: 'admin')),
            ]);
    }
}
