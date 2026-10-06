<?php

namespace App;

/**
 * Public leaderboard window. Dates use the application timezone.
 */
enum RankingPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * First calendar day included in this window. The day board is that day only.
     */
    public function startDate(): string
    {
        return match ($this) {
            self::Day => today()->toDateString(),
            self::Week => today()->subDays(6)->toDateString(),
            self::Month => today()->subDays(29)->toDateString(),
        };
    }

    public function isSingleDay(): bool
    {
        return $this === self::Day;
    }

    public static function fromRouteName(?string $name): self
    {
        return match ($name) {
            'rankings.week' => self::Week,
            'rankings.month' => self::Month,
            default => self::Day,
        };
    }
}
