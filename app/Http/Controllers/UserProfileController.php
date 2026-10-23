<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Support\GamePresenter;
use App\Support\PageSeo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    private const RESOURCES_PER_PAGE = 12;

    private const FAVORITES_PER_PAGE = 8;

    public function show(Request $request, User $user): RedirectResponse|Response
    {
        return $this->canonicalRedirect($request, $user, 'users.show')
            ?? $this->tab($request, $user, 'resources');
    }

    public function favorites(Request $request, User $user): RedirectResponse|Response
    {
        return $this->canonicalRedirect($request, $user, 'users.favorites')
            ?? $this->tab($request, $user, 'favorites');
    }

    /**
     * Serve a tab, or send the visitor to the one section the owner kept public.
     *
     * @param  'resources'|'favorites'  $activeTab
     */
    private function tab(Request $request, User $user, string $activeTab): RedirectResponse|Response
    {
        $canViewResources = $this->tabIsVisible($request, $user, 'resources');
        $canViewFavorites = $this->tabIsVisible($request, $user, 'favorites');

        // Nothing public: keep the profile header and say so instead of a bare 404.
        if (! $canViewResources && ! $canViewFavorites) {
            return $this->page($request, $user, $activeTab);
        }

        if ($this->tabIsVisible($request, $user, $activeTab)) {
            return $this->page($request, $user, $activeTab);
        }

        $other = $activeTab === 'resources' ? 'favorites' : 'resources';

        return to_route($other === 'resources' ? 'users.show' : 'users.favorites', $user);
    }

    /**
     * Owners always see their own tabs; everyone else respects the privacy flags.
     *
     * @param  'resources'|'favorites'  $tab
     */
    private function tabIsVisible(Request $request, User $user, string $tab): bool
    {
        if ($request->user()?->id === $user->id) {
            return true;
        }

        return $tab === 'resources' ? ! $user->hide_resources : ! $user->hide_favorites;
    }

    /**
     * @param  'resources'|'favorites'  $activeTab
     */
    private function page(Request $request, User $user, string $activeTab): Response
    {
        $isOwner = $request->user()?->id === $user->id;
        $canViewResources = $this->tabIsVisible($request, $user, 'resources');
        $canViewFavorites = $this->tabIsVisible($request, $user, 'favorites');

        return Inertia::render('users/show', [
            'profile' => $user->toPublicProfile(),
            'activeTab' => $activeTab,
            'isOwner' => $isOwner,
            'canViewResources' => $canViewResources,
            'canViewFavorites' => $canViewFavorites,
            // Both sections private for this visitor: header only, no content.
            'sectionsHidden' => ! $canViewResources && ! $canViewFavorites,
            // Only used to remind the owner that a section is private.
            'hiddenResources' => $user->hide_resources,
            'hiddenFavorites' => $user->hide_favorites,
            // Hidden tabs report zero so a private count never leaks.
            'resourcesCount' => $canViewResources
                ? $this->contributedGamesQuery($user)->count()
                : 0,
            'favoritesCount' => $canViewFavorites
                ? $user->favoritedGames()->published()->count()
                : 0,
            'resources' => $activeTab === 'resources' && $canViewResources
                ? $this->presentResources($user)
                : null,
            'favorites' => $activeTab === 'favorites' && $canViewFavorites
                ? $this->presentFavorites($user, $isOwner)
                : null,
            'downloadUpdateCount' => $activeTab === 'favorites' && $isOwner
                ? $this->downloadUpdateCount($user)
                : 0,
            'pageSeo' => PageSeo::noindex(
                $activeTab === 'favorites'
                    ? $user->name.' · Favorites'
                    : $user->name,
                $activeTab === 'favorites'
                    ? route('users.favorites', $user)
                    : route('users.show', $user),
            ),
        ]);
    }

    private function canonicalRedirect(Request $request, User $user, string $route): ?RedirectResponse
    {
        $parameter = $request->route()?->originalParameter('user');

        if ($parameter === $user->slug) {
            return null;
        }

        return redirect()->route($route, [
            'user' => $user,
            ...$request->query(),
        ], 301);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function presentResources(User $user)
    {
        return $this->contributedGamesQuery($user)
            ->withCardData()
            ->latest('id')
            ->paginate(self::RESOURCES_PER_PAGE)
            ->withQueryString()
            ->through(fn (Game $game): array => GamePresenter::card($game));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function presentFavorites(User $user, bool $isOwner)
    {
        return $user->favoritedGames()
            ->published()
            ->withCardData()
            ->orderByPivot('created_at', 'desc')
            ->paginate(self::FAVORITES_PER_PAGE)
            ->withQueryString()
            ->through(fn (Game $game): array => [
                ...GamePresenter::card($game),
                'hasDownloadUpdate' => $isOwner && $game->hasUnreadDownloadUpdate(),
            ]);
    }

    private function downloadUpdateCount(User $user): int
    {
        return $user->favoritedGames()
            ->published()
            ->whereNotNull('games.downloads_updated_at')
            ->whereRaw(
                'games.downloads_updated_at > COALESCE(favorites.downloads_seen_at, favorites.created_at)',
            )
            ->count();
    }

    /** @return Builder<Game> */
    private function contributedGamesQuery(User $user): Builder
    {
        return Game::query()
            ->published()
            ->whereHas(
                'releases',
                fn ($releases) => $releases
                    ->where('user_id', $user->id)
                    ->available(),
            );
    }
}
