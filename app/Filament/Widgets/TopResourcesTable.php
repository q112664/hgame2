<?php

namespace App\Filament\Widgets;

use App\Actions\Stats\BuildTopResourcesQuery;
use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopResourcesTable extends TableWidget
{
    protected static ?string $heading = 'Top resources (7 days)';

    protected int|string|array $columnSpan = 1;

    protected int $defaultPaginationPageOption = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(BuildTopResourcesQuery::class)())
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(36)
                    ->tooltip(fn (Game $record): string => $record->title),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('recent_views')
                    ->label('Views (7d)')
                    ->numeric()
                    // The list arrives ordered by a weighted score, which would
                    // outrank anything the reader asks for, so the score has to
                    // go before the column's own order takes over.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->reorder()
                        ->orderBy('recent_daily.recent_views', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('recent_downloads')
                    ->label('Downloads (7d)')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->reorder()
                        ->orderBy('recent_daily.recent_downloads', $direction === 'desc' ? 'desc' : 'asc')),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->url(fn (Game $record): string => GameResource::getUrl('edit', ['record' => $record], panel: 'admin')),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateIcon(Heroicon::OutlinedChartBarSquare)
            ->emptyStateHeading('No traffic recorded yet')
            ->emptyStateDescription('Per-day totals start from the day these counters were introduced, so the first week is quiet.')
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all')
                    ->url(GameResource::getUrl(panel: 'admin')),
            ]);
    }
}
