<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentUsersTable extends TableWidget
{
    protected static ?string $heading = 'Recent users';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => User::query()
                    ->latest('created_at')
                    ->latest('id'),
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->limit(24),
                TextColumn::make('email')
                    ->label('Email')
                    ->limit(28)
                    ->toggleable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('manage')
                    ->label('Open')
                    ->url(UserResource::getUrl(panel: 'admin')),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all')
                    ->url(UserResource::getUrl(panel: 'admin')),
            ]);
    }
}
