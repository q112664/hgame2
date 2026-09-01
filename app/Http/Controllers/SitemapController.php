<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Doc;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use App\Support\TaxonomyDirectory;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => route('home'),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => route('resources.index'),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => route('resources.tags'),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ],
            [
                'loc' => route('docs.index'),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
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
            $urls[] = [
                'loc' => route('resources.show', $game),
                // Content lastmod: download updates, else site publish — not updated_at.
                'lastmod' => $game->contentModifiedAt()?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        $docs = Doc::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at']);

        foreach ($docs as $doc) {
            $urls[] = [
                'loc' => route('docs.show', $doc),
                'lastmod' => ($doc->updated_at ?? $doc->published_at)?->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    private function taxonomyUrls(): array
    {
        $urls = [];

        $categories = Category::query()
            ->whereHas('games', TaxonomyDirectory::publishedGamesConstraint(...))
            ->orderBy('name')
            ->get(['slug']);

        foreach ($categories as $category) {
            $urls[] = [
                'loc' => route('resources.genre', $category),
                'changefreq' => 'daily',
                'priority' => '0.75',
            ];
        }

        $platforms = Platform::query()
            ->whereHas(
                'releases.game',
                TaxonomyDirectory::publishedGamesConstraint(...),
            )
            ->orderBy('name')
            ->get(['slug']);

        foreach ($platforms as $platform) {
            $urls[] = [
                'loc' => route('resources.platform', $platform),
                'changefreq' => 'daily',
                'priority' => '0.75',
            ];
        }

        $languages = Language::query()
            ->whereHas(
                'releases.game',
                TaxonomyDirectory::publishedGamesConstraint(...),
            )
            ->orderBy('name')
            ->get(['code']);

        foreach ($languages as $language) {
            $urls[] = [
                'loc' => route('resources.language', $language),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
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
            $urls[] = [
                'loc' => route('resources.tag', $tag),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        return $urls;
    }
}
