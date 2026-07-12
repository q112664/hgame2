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
            ->with($this->cardRelations())
            ->latest('published_at')
            ->get();

        return Inertia::render('welcome', [
            'resources' => $games->map(GamePresenter::card(...))->values(),
        ]);
    }

    /** @return array<string, callable> */
    private function cardRelations(): array
    {
        return [
            'category' => fn ($query) => $query->select(['id', 'name']),
            'tags' => fn ($query) => $query->select(['tags.id', 'name']),
            'releases' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('downloadLinks', fn ($links) => $links->where('is_active', true))
                ->with([
                    'platforms:id,name,slug',
                    'languages:id,name',
                    'downloadLinks' => fn ($links) => $links->where('is_active', true),
                ]),
        ];
    }
}
