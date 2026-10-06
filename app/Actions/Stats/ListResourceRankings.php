<?php

namespace App\Actions\Stats;

use App\Models\Game;
use App\Models\GameDailyStat;
use App\RankingPeriod;
use App\Support\GamePresenter;
use Illuminate\Database\Eloquent\Builder;

class ListResourceRankings
{
    public const int Limit = 30;

    /**
     * Published games ranked by views plus downloads inside one window.
     *
     * Views and downloads weigh the same. The admin 7-day table keeps its own
     * download weight and is not this list.
     *
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     subtitle: string|null,
     *     thumbnail: string,
     *     thumbnailFallback: string,
     *     developer: string,
     *     category: string,
     *     views: int,
     *     downloads: int,
     *     rank: int
     * }>
     */
    public function __invoke(RankingPeriod $period): array
    {
        $daily = GameDailyStat::query()
            ->when(
                $period->isSingleDay(),
                fn (Builder $query): Builder => $query->where('date', $period->startDate()),
                fn (Builder $query): Builder => $query->where('date', '>=', $period->startDate()),
            )
            ->groupBy('game_id')
            ->select('game_id')
            ->selectRaw('SUM(views) as period_views, SUM(downloads) as period_downloads')
            ->toBase();

        $games = Game::query()
            ->published()
            ->joinSub($daily, 'period_daily', 'period_daily.game_id', '=', 'games.id')
            ->with('category:id,name')
            ->select([
                'games.id',
                'games.slug',
                'games.title',
                'games.subtitle',
                'games.developer',
                'games.cover_path',
                'games.cover_url',
                'games.category_id',
            ])
            ->selectRaw('period_daily.period_views as period_views, period_daily.period_downloads as period_downloads')
            ->whereRaw('(period_daily.period_views + period_daily.period_downloads) > 0')
            ->orderByRaw('(period_daily.period_views + period_daily.period_downloads) desc')
            ->orderByDesc('period_daily.period_downloads')
            ->orderByDesc('period_daily.period_views')
            ->orderByDesc('games.id')
            ->limit(self::Limit)
            ->get();

        return $games
            ->values()
            ->map(fn (Game $game, int $index): array => GamePresenter::rankingEntry(
                $game,
                (int) $game->getAttribute('period_views'),
                (int) $game->getAttribute('period_downloads'),
                $index + 1,
            ))
            ->all();
    }
}
