<?php

namespace App\Http\Controllers;

use App\GameStatus;
use App\Models\GameDownloadLink;
use Inertia\Inertia;
use Inertia\Response;

class DownloadLinkController extends Controller
{
    public function show(GameDownloadLink $downloadLink): Response
    {
        $downloadLink->load([
            'release.game.category:id,name',
            'release.platforms:id,name,slug',
            'release.languages:id,name,code',
        ]);

        $release = $downloadLink->release;
        $game = $release?->game;

        abort_if($release === null || $game === null, 404);
        abort_unless($downloadLink->is_active, 404);
        abort_unless(
            $game->status === GameStatus::Published
            && $game->published_at !== null
            && $game->published_at->lte(now()),
            404,
        );

        $host = parse_url((string) $downloadLink->url, PHP_URL_HOST);

        return Inertia::render('download-links/show', [
            'resource' => [
                'id' => $game->slug,
                'title' => $game->title,
                'subtitle' => $game->subtitle,
                'category' => $game->category?->name ?? 'Uncategorized',
            ],
            'release' => [
                'id' => $release->id,
                'title' => $release->title ?: null,
                'version' => $release->version,
                'fileSize' => $release->file_size,
                'platforms' => $release->platforms
                    ->map(fn ($platform): array => [
                        'name' => $platform->name,
                        'slug' => $platform->slug,
                    ])
                    ->values()
                    ->all(),
            ],
            'link' => [
                'id' => $downloadLink->id,
                'label' => $downloadLink->label ?: 'Download',
                'url' => $downloadLink->url,
                'host' => is_string($host) && $host !== '' ? $host : null,
            ],
        ]);
    }
}
