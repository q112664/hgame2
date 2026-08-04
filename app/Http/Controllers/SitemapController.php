<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Game;
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
                'loc' => route('docs.index'),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
        ];

        $games = Game::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['slug', 'published_at', 'updated_at']);

        foreach ($games as $game) {
            $urls[] = [
                'loc' => route('resources.details', $game),
                'lastmod' => ($game->updated_at ?? $game->published_at)?->toAtomString(),
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
}
