<?php

namespace App\Actions\Stats;

use App\Models\GameDailyStat;

class BuildDailyTrafficSeries
{
    public const int Days = 30;

    /**
     * Site-wide views and downloads per day, oldest day first.
     *
     * Every day in the window gets a slot even when nothing happened. Plotting
     * only the days that have rows would drop the quiet days out of the axis and
     * read as a broken chart rather than a slow week.
     *
     * @return array{dates: list<string>, views: list<int>, downloads: list<int>}
     */
    public function __invoke(?int $days = null): array
    {
        $days ??= self::Days;
        $today = today();
        $start = $today->copy()->subDays($days - 1);

        $totals = GameDailyStat::query()
            ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
            ->groupBy('date')
            ->select('date')
            ->selectRaw('SUM(views) as total_views, SUM(downloads) as total_downloads')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                // Drivers return either a date or a datetime string; the series
                // is keyed by the calendar day either way.
                substr((string) $row->date, 0, 10) => [
                    'views' => (int) $row->total_views,
                    'downloads' => (int) $row->total_downloads,
                ],
            ]);

        $dates = [];
        $views = [];
        $downloads = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $day = $totals->get($date);

            $dates[] = $date;
            $views[] = $day['views'] ?? 0;
            $downloads[] = $day['downloads'] ?? 0;
        }

        return ['dates' => $dates, 'views' => $views, 'downloads' => $downloads];
    }
}
