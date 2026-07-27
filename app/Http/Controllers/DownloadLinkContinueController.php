<?php

namespace App\Http\Controllers;

use App\GameStatus;
use App\Models\GameDownloadLink;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DownloadLinkContinueController extends Controller
{
    public function __invoke(Request $request, GameDownloadLink $downloadLink): RedirectResponse
    {
        $downloadLink->load(['release.game']);

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

        if (Turnstile::isEnabled(Turnstile::FEATURE_DOWNLOAD)) {
            $request->validate(Turnstile::validationRules(Turnstile::FEATURE_DOWNLOAD));
            Turnstile::validateRequest(Turnstile::FEATURE_DOWNLOAD);
        }

        return redirect()->away($downloadLink->url);
    }
}
