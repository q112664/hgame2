<?php

namespace App\Filament\Widgets;

use App\DocStatus;
use App\Filament\Resources\Docs\DocResource;
use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\Users\UserResource;
use App\GameStatus;
use App\Models\Doc;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class SiteStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $publishedGames = Game::query()->where('status', GameStatus::Published)->count();
        $draftGames = Game::query()->where('status', GameStatus::Draft)->count();
        $unlistedGames = Game::query()->where('status', GameStatus::Unlisted)->count();

        $usersTotal = User::query()->count();
        $usersNewWeek = User::query()->where('created_at', '>=', now()->subDays(7))->count();
        $admins = User::query()->where('is_admin', true)->count();

        $viewsTotal = (int) Game::query()->sum('views_count');
        $downloadsTotal = (int) Game::query()->sum('downloads_count');

        $commentsWeek = GameComment::query()->where('created_at', '>=', now()->subDays(7))->count();
        $favoritesTotal = (int) DB::table('favorites')->count();

        $publishedDocs = Doc::query()->where('status', DocStatus::Published)->count();

        $gamesPublishedWeek = Game::query()
            ->where('status', GameStatus::Published)
            ->where('published_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Published resources', Number::format($publishedGames))
                ->description($gamesPublishedWeek.' published this week · '.$draftGames.' draft · '.$unlistedGames.' unlisted')
                ->descriptionIcon(Heroicon::OutlinedPuzzlePiece)
                ->color('success')
                ->url(GameResource::getUrl(panel: 'admin')),

            Stat::make('Users', Number::format($usersTotal))
                ->description($usersNewWeek.' new in 7 days · '.$admins.' admin')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary')
                ->url(UserResource::getUrl(panel: 'admin')),

            Stat::make('Total views', Number::format($viewsTotal))
                ->description(Number::format($downloadsTotal).' recorded downloads')
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color('info'),

            Stat::make('Engagement', Number::format($favoritesTotal).' favorites')
                ->description($commentsWeek.' comments in 7 days · '.$publishedDocs.' published docs')
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('warning')
                ->url(DocResource::getUrl(panel: 'admin')),
        ];
    }
}
