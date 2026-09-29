<?php

namespace App\Filament\Widgets;

use App\Actions\Stats\BuildDailyTrafficSeries;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DailyTrafficChart extends ChartWidget
{
    protected ?string $heading = 'Views and downloads';

    protected ?string $description = 'Per day, site-wide. History starts the day these counters were introduced.';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = app(BuildDailyTrafficSeries::class)();

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => $series['views'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                ],
                [
                    'label' => 'Downloads',
                    'data' => $series['downloads'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => array_map(
                fn (string $date): string => Carbon::parse($date)->format('M j'),
                $series['dates'],
            ),
        ];
    }

    /**
     * Filament draws charts with Chart.js, so these are Chart.js options: a
     * line's curve lives under `elements.line`. A line dataset draws no fill by
     * default, so an area chart is opt-in rather than something to switch off.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'elements' => [
                'line' => ['tension' => 0.35],
            ],
        ];
    }
}
