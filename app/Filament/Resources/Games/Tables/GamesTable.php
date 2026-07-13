<?php

namespace App\Filament\Resources\Games\Tables;

use App\GameStatus;
use App\Models\Language;
use App\Models\Platform;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('developer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('release_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('releases_count')
                    ->counts('releases')
                    ->label('Releases'),
                TextColumn::make('views_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('downloads_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(GameStatus::class),
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                SelectFilter::make('platform')
                    ->options(fn (): array => Platform::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $platformId): Builder => $query
                            ->whereHas('releases.platforms', fn (Builder $platforms): Builder => $platforms->whereKey($platformId)),
                    )),
                SelectFilter::make('language')
                    ->options(fn (): array => Language::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $languageId): Builder => $query
                            ->whereHas('releases.languages', fn (Builder $languages): Builder => $languages->whereKey($languageId)),
                    )),
                SelectFilter::make('availability')
                    ->label('Download availability')
                    ->options([
                        'available' => 'Has active downloads',
                        'missing' => 'No active downloads',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $hasDownloads = fn (Builder $releases): Builder => $releases
                            ->where('is_active', true)
                            ->whereHas('downloadLinks', fn (Builder $links): Builder => $links->where('is_active', true));

                        return match ($data['value'] ?? null) {
                            'available' => $query->whereHas('releases', $hasDownloads),
                            'missing' => $query->whereDoesntHave('releases', $hasDownloads),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
