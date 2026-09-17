<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Setting;
use App\Support\GamePresenter;
use App\Support\PageSeo;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const int HomeSectionLimit = 12;

    private const int PopularLimit = 8;

    private const int PopularWindowDays = 30;

    public function __invoke(): Response
    {
        $latestGames = Game::query()
            ->published()
            ->withCardData()
            ->latest('published_at')
            ->orderByDesc('id')
            ->limit(self::HomeSectionLimit + 1)
            ->get();

        return Inertia::render('welcome', [
            'hero' => Setting::homeHero(),
            'pageSeo' => PageSeo::home(),
            'popular' => $this->presentCards(
                Game::query()
                    ->published()
                    ->withCardData()
                    // “Popular” is a rolling window, so an old favourite cannot
                    // sit there forever on lifetime views.
                    ->where('published_at', '>=', now()->subDays(self::PopularWindowDays))
                    ->orderByDesc('views_count')
                    ->orderByDesc('downloads_count')
                    ->orderByDesc('published_at')
                    ->limit(self::PopularLimit)
                    ->get(),
            ),
            // New listings on this site (published_at) — not download updates.
            'resources' => $this->presentCards(
                $latestGames->take(self::HomeSectionLimit),
            ),
            'hasMoreResources' => $latestGames->count() > self::HomeSectionLimit,
        ]);
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return list<array<string, mixed>>
     */
    private function presentCards(Collection $games): array
    {
        return array_values(
            $games
                ->map(fn (Game $game): array => GamePresenter::card($game))
                ->all(),
        );
    }
}
