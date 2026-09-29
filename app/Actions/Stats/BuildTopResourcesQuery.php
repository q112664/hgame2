<?php

namespace App\Actions\Stats;

use App\Models\Game;
use App\Models\GameDailyStat;
use Illuminate\Database\Eloquent\Builder;

class BuildTopResourcesQuery
{
    public const int Days = 7;

    /**
     * A download carries more intent than a view and is far rarer, so it is
     * weighted before ranking. Added up as they are, downloads would be lost in
     * the noise of views and the list would just be a views leaderboard.
     */
    public const int DownloadWeight = 5;

    /**
     * Games ranked by the traffic they earned over the last few days.
     *
     * Only published resources are eligible: drafts and unlisted pages are
     * reachable by slug and do record traffic, and an admin preview is not an
     * audience.
     *
     * @return Builder<Game>
     */
    public function __invoke(?int $days = null): Builder
    {
        $days ??= self::Days;
        $since = today()->subDays($days - 1)->toDateString();

        $daily = GameDailyStat::query()
            ->where('date', '>=', $since)
            ->groupBy('game_id')
            ->select('game_id')
            ->selectRaw('SUM(views) as recent_views, SUM(downloads) as recent_downloads')
            ->toBase();

        return Game::query()
            ->published()
            ->joinSub($daily, 'recent_daily', 'recent_daily.game_id', '=', 'games.id')
            ->select('games.*')
            ->selectRaw('recent_daily.recent_views as recent_views, recent_daily.recent_downloads as recent_downloads')
            ->orderByRaw(
                '(recent_daily.recent_views + recent_daily.recent_downloads * ?) desc',
                [self::DownloadWeight],
            );
    }
}
