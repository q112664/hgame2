<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\GamePresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $games = Game::query()
            ->published()
            ->withCardData()
            ->latest('published_at')
            ->limit(12)
            ->get();

        return Inertia::render('welcome', [
            'resources' => $games
                ->map(fn (Game $game): array => GamePresenter::card($game))
                ->values(),
        ]);
    }
}
