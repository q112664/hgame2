<?php

namespace App\Filament\Widgets;

use App\Actions\Stats\BuildDailyTrafficSeries;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class SiteStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * Traffic is read off the same 30-day series the chart draws, so the cards
     * and the chart can never disagree about what today added up to.
     */
    protected function getStats(): array
    {
        $series = app(BuildDailyTrafficSeries::class)();

        $usersTotal = User::query()->count();
        $usersNewWeek = User::query()->where('created_at', '>=', now()->subDays(7))->count();
        $admins = User::query()->where('is_admin', true)->count();

        return [
            $this->trafficStat(
                label: 'Views today',
                color: 'info',
                icon: Heroicon::OutlinedEye,
                values: $series['views'],
                dates: $series['dates'],
            ),

            $this->trafficStat(
                label: 'Downloads today',
                color: 'success',
                icon: Heroicon::OutlinedArrowDownTray,
                values: $series['downloads'],
                dates: $series['dates'],
            ),

            Stat::make('Users', Number::format($usersTotal))
                ->description($usersNewWeek.' new in 7 days · '.$admins.' admin')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary')
                ->url(UserResource::getUrl(panel: 'admin')),
        ];
    }

    /**
     * @param  list<int>  $values  One entry per day, oldest first, ending today.
     * @param  list<string>  $dates  The matching `Y-m-d` labels.
     */
    private function trafficStat(
        string $label,
        string $color,
        Heroicon $icon,
        array $values,
        array $dates,
    ): Stat {
        $lastIndex = count($values) - 1;
        $today = $values[$lastIndex];
        $yesterday = $values[$lastIndex - 1] ?? 0;
        $week = array_sum(array_slice($values, -7));

        $sparkline = [];

        foreach (array_slice($dates, -7, preserve_keys: true) as $index => $date) {
            $sparkline[Carbon::parse($date)->format('M j')] = $values[$index];
        }

        return Stat::make($label, Number::format($today))
            ->description($this->trafficDescription($today, $yesterday, $week))
            ->descriptionIcon($this->trendIcon($today, $yesterday))
            ->color($color)
            ->chart($sparkline)
            ->chartColor($color);
    }

    private function trafficDescription(int $today, int $yesterday, int $week): string
    {
        $weekTotal = Number::format($week);

        // A percentage of zero is not a trend. Without this the first morning
        // after going live would report a meaningless jump or a flat 0%.
        if ($yesterday === 0) {
            return 'Nothing yesterday · '.$weekTotal.' in 7 days';
        }

        $change = (int) round((($today - $yesterday) / $yesterday) * 100);

        return sprintf(
            '%+d%% vs yesterday (%s) · %s in 7 days',
            $change,
            Number::format($yesterday),
            $weekTotal,
        );
    }

    private function trendIcon(int $today, int $yesterday): Heroicon
    {
        if ($yesterday === 0) {
            return Heroicon::OutlinedMinus;
        }

        return $today >= $yesterday
            ? Heroicon::OutlinedArrowTrendingUp
            : Heroicon::OutlinedArrowTrendingDown;
    }
}
