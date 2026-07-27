<?php

namespace App\Http\Controllers;

use App\GameStatus;
use App\Models\GameDownloadLink;
use App\Support\Turnstile;
use Inertia\Inertia;
use Inertia\Response;

class DownloadLinkController extends Controller
{
    public function show(GameDownloadLink $downloadLink): Response
    {
        $downloadLink->load(['release.game:id,slug,title,status,published_at']);

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
        $requiresTurnstile = Turnstile::isEnabled(Turnstile::FEATURE_DOWNLOAD);

        return Inertia::render('download-links/show', [
            'resource' => [
                'id' => $game->slug,
                'title' => $game->title,
            ],
            'link' => [
                'id' => $downloadLink->id,
                'label' => $downloadLink->label ?: 'Download',
                // Hide the real URL until Turnstile is verified when required.
                'url' => $requiresTurnstile ? null : $downloadLink->url,
                'host' => is_string($host) && $host !== '' ? $host : null,
                'requiresTurnstile' => $requiresTurnstile,
            ],
        ]);
    }
}
