<?php

namespace App\Filament\Pages;

use App\Support\RedisStatus;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ViewRedisStatus extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Redis status';

    protected static ?string $title = 'Redis status';

    protected static ?string $slug = 'redis-status';

    protected static ?int $navigationSort = 4;

    /**
     * @var view-string
     */
    protected string $view = 'filament.pages.view-redis-status';

    /**
     * @var array<string, mixed>
     */
    public array $status = [];

    public static function shouldRegisterNavigation(): bool
    {
        return RedisStatus::isConfigured();
    }

    public static function canAccess(): bool
    {
        return RedisStatus::isConfigured();
    }

    public function mount(): void
    {
        $this->status = RedisStatus::snapshot();
    }

    public function refreshStatus(): void
    {
        $this->status = RedisStatus::snapshot();
    }
}
