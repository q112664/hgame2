<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Setting;
use App\Support\GamePresenter;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const int HomeSectionLimit = 12;

    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'heroBackgroundUrl' => Setting::heroBackgroundUrl(),
            'recentReleases' => $this->presentCards(
                Game::query()
                    ->published()
                    ->withCardData()
                    ->whereNotNull('release_date')
                    ->latest('release_date')
                    ->orderByDesc('id')
                    ->limit(self::HomeSectionLimit)
                    ->get(),
            ),
            'resources' => $this->presentCards(
                Game::query()
                    ->published()
                    ->withCardData()
                    ->latest('published_at')
                    ->orderByDesc('id')
                    ->limit(self::HomeSectionLimit)
                    ->get(),
            ),
        ]);
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return list<array<string, mixed>>
     */
    private function presentCards(Collection $games): array
    {
        return $games
            ->map(fn (Game $game): array => GamePresenter::card($game))
            ->values()
            ->all();
    }
}
