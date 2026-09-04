<?php

namespace App\Http\Controllers;

use App\GameStatus;
use App\Models\Category;
use App\Models\Doc;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use App\Support\TaxonomyDirectory;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $catalogLastmod = $this->catalogLastmod();
        $docsLastmod = $this->docsIndexLastmod();

        $urls = [
            $this->url(route('home'), 'daily', '1.0', $catalogLastmod),
            $this->url(route('resources.index'), 'daily', '0.9', $catalogLastmod),
            $this->url(route('resources.tags'), 'daily', '0.8', $catalogLastmod),
            $this->url(route('docs.index'), 'weekly', '0.6', $docsLastmod),
        ];

        foreach ($this->taxonomyUrls() as $taxonomyUrl) {
            $urls[] = $taxonomyUrl;
        }

        $games = Game::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['slug', 'published_at', 'downloads_updated_at']);

        foreach ($games as $game) {
            $urls[] = $this->url(
                route('resources.show', $game),
                'weekly',
                '0.8',
                $game->contentModifiedAt()?->toAtomString(),
            );
        }

        $docs = Doc::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at']);

        foreach ($docs as $doc) {
            $urls[] = $this->url(
                route('docs.show', $doc),
                'monthly',
                '0.5',
                ($doc->updated_at ?? $doc->published_at)?->toAtomString(),
            );
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @return list<array{loc: string, changefreq: string, priority: string, lastmod?: string}>
     */
    private function taxonomyUrls(): array
    {
        $urls = [];
        $categoryLastmods = $this->lastmodsByCategory();
        $platformLastmods = $this->lastmodsByPlatform();
        $languageLastmods = $this->lastmodsByLanguage();
        $tagLastmods = $this->lastmodsByTag();

        $categories = Category::query()
            ->whereHas('games', TaxonomyDirectory::publishedGamesConstraint(...))
            ->orderBy('name')
            ->get(['id', 'slug']);

        foreach ($categories as $category) {
            $urls[] = $this->url(
                route('resources.genre', $category),
                'daily',
                '0.75',
                $categoryLastmods[$category->id] ?? null,
            );
        }

        $platforms = Platform::query()
            ->whereHas(
                'releases.game',
                TaxonomyDirectory::publishedGamesConstraint(...),
            )
            ->orderBy('name')
            ->get(['id', 'slug']);

        foreach ($platforms as $platform) {
            $urls[] = $this->url(
                route('resources.platform', $platform),
                'daily',
                '0.75',
                $platformLastmods[$platform->id] ?? null,
            );
        }

        $languages = Language::query()
            ->whereHas(
                'releases.game',
                TaxonomyDirectory::publishedGamesConstraint(...),
            )
            ->orderBy('name')
            ->get(['id', 'code']);

        foreach ($languages as $language) {
            $urls[] = $this->url(
                route('resources.language', $language),
                'daily',
                '0.7',
                $languageLastmods[$language->id] ?? null,
            );
        }

        $publishedGames = fn ($query) => $query->published();
        $tags = Tag::query()
            ->whereHas('games', $publishedGames)
            ->withCount(['games' => $publishedGames])
            ->orderBy('name')
            ->get(['id', 'slug'])
            ->filter(
                fn (Tag $tag): bool => TaxonomyDirectory::isIndexablePublishedCount(
                    (int) $tag->games_count,
                ),
            );

        foreach ($tags as $tag) {
            $urls[] = $this->url(
                route('resources.tag', $tag),
                'weekly',
                '0.7',
                $tagLastmods[$tag->id] ?? null,
            );
        }

        return $urls;
    }

    /**
     * @return array{loc: string, changefreq: string, priority: string, lastmod?: string}
     */
    private function url(string $loc, string $changefreq, string $priority, ?string $lastmod): array
    {
        $url = [
            'loc' => $loc,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];

        if ($lastmod !== null && $lastmod !== '') {
            $url['lastmod'] = $lastmod;
        }

        return $url;
    }

    private function catalogLastmod(): ?string
    {
        return $this->atomLastmod(
            Game::query()
                ->published()
                ->toBase()
                ->selectRaw('MAX(COALESCE(games.downloads_updated_at, games.published_at)) as lastmod')
                ->value('lastmod'),
        );
    }

    private function docsIndexLastmod(): ?string
    {
        return $this->atomLastmod(
            Doc::query()
                ->published()
                ->toBase()
                ->selectRaw('MAX(COALESCE(updated_at, published_at)) as lastmod')
                ->value('lastmod'),
        );
    }

    /**
     * @return array<int, string>
     */
    private function lastmodsByCategory(): array
    {
        return $this->atomLastmodMap(
            Game::query()
                ->published()
                ->toBase()
                ->whereNotNull('category_id')
                ->selectRaw('category_id, MAX(COALESCE(games.downloads_updated_at, games.published_at)) as lastmod')
                ->groupBy('category_id')
                ->pluck('lastmod', 'category_id'),
        );
    }

    /**
     * @return array<int, string>
     */
    private function lastmodsByPlatform(): array
    {
        return $this->atomLastmodMap(
            Game::query()
                ->toBase()
                ->join('game_releases', 'games.id', '=', 'game_releases.game_id')
                ->join('game_release_platform', 'game_releases.id', '=', 'game_release_platform.game_release_id')
                ->where('games.status', GameStatus::Published)
                ->whereNotNull('games.published_at')
                ->where('games.published_at', '<=', now())
                ->selectRaw('game_release_platform.platform_id as id, MAX(COALESCE(games.downloads_updated_at, games.published_at)) as lastmod')
                ->groupBy('game_release_platform.platform_id')
                ->pluck('lastmod', 'id'),
        );
    }

    /**
     * @return array<int, string>
     */
    private function lastmodsByLanguage(): array
    {
        return $this->atomLastmodMap(
            Game::query()
                ->toBase()
                ->join('game_releases', 'games.id', '=', 'game_releases.game_id')
                ->join('game_release_language', 'game_releases.id', '=', 'game_release_language.game_release_id')
                ->where('games.status', GameStatus::Published)
                ->whereNotNull('games.published_at')
                ->where('games.published_at', '<=', now())
                ->selectRaw('game_release_language.language_id as id, MAX(COALESCE(games.downloads_updated_at, games.published_at)) as lastmod')
                ->groupBy('game_release_language.language_id')
                ->pluck('lastmod', 'id'),
        );
    }

    /**
     * @return array<int, string>
     */
    private function lastmodsByTag(): array
    {
        return $this->atomLastmodMap(
            Game::query()
                ->toBase()
                ->join('game_tag', 'games.id', '=', 'game_tag.game_id')
                ->where('games.status', GameStatus::Published)
                ->whereNotNull('games.published_at')
                ->where('games.published_at', '<=', now())
                ->selectRaw('game_tag.tag_id as id, MAX(COALESCE(games.downloads_updated_at, games.published_at)) as lastmod')
                ->groupBy('game_tag.tag_id')
                ->pluck('lastmod', 'id'),
        );
    }

    /**
     * @param  Collection<array-key, mixed>  $values
     * @return array<int, string>
     */
    private function atomLastmodMap(Collection $values): array
    {
        $map = [];

        foreach ($values as $id => $value) {
            $lastmod = $this->atomLastmod($value);

            if ($lastmod !== null) {
                $map[(int) $id] = $lastmod;
            }
        }

        return $map;
    }

    private function atomLastmod(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->toAtomString();
    }
}
