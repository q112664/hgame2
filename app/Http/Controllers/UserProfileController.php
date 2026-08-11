<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Support\GamePresenter;
use App\Support\PageSeo;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    private const PER_PAGE = 12;

    public function show(User $user): Response
    {
        $resources = Game::query()
            ->published()
            ->whereHas(
                'releases',
                fn ($releases) => $releases
                    ->where('user_id', $user->id)
                    ->available(),
            )
            ->withCardData()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Game $game): array => GamePresenter::card($game));

        return Inertia::render('users/show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ],
            'resources' => $resources,
            'pageSeo' => PageSeo::noindex(
                $user->name,
                route('users.show', $user),
            ),
        ]);
    }
}
