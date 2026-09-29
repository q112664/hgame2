<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DailyTrafficChart;
use App\Filament\Widgets\RecentUsersTable;
use App\Filament\Widgets\SiteStatsOverview;
use App\Filament\Widgets\TopResourcesTable;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            SiteStatsOverview::class,
            DailyTrafficChart::class,
            TopResourcesTable::class,
            RecentUsersTable::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 2;
    }
}
