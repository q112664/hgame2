<?php

namespace App\Http\Controllers;

use App\GameStatus;
use App\Models\GameDownloadLink;
use App\Support\Media;
use App\Support\MediaThumbnail;
use App\Support\PageSeo;
use App\Support\Turnstile;
use Inertia\Inertia;
use Inertia\Response;

class DownloadLinkController extends Controller
{
    public function show(GameDownloadLink $downloadLink): Response
    {
        $downloadLink->load(['release.game:id,slug,title,status,published_at,cover_path,cover_url']);

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
                'thumbnail' => $this->thumbnailUrl($game->cover_path, $game->cover_url),
            ],
            'link' => [
                'id' => $downloadLink->id,
                'label' => $downloadLink->label ?: 'Download',
                // Hide the real URL until Turnstile is verified when required.
                'url' => $requiresTurnstile ? null : $downloadLink->url,
                'host' => is_string($host) && $host !== '' ? $host : null,
                'requiresTurnstile' => $requiresTurnstile,
            ],
            'pageSeo' => PageSeo::noindex(
                'Download — '.$game->title,
                route('download-links.show', $downloadLink),
            ),
        ]);
    }

    private function thumbnailUrl(?string $coverPath, ?string $coverUrl): string
    {
        if (filled($coverPath)) {
            return MediaThumbnail::url((string) $coverPath);
        }

        return Media::url($coverUrl);
    }
}
