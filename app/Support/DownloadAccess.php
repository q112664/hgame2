<?php

namespace App\Support;

use App\Models\GameDownloadLink;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DownloadAccess
{
    /**
     * Send a guest to login when downloads require an account.
     * The jump page is stored as the intended URL so login returns there.
     */
    public static function guestRedirect(Request $request, GameDownloadLink $downloadLink): ?RedirectResponse
    {
        if (! Setting::requireLoginToDownload() || $request->user() !== null) {
            return null;
        }

        $request->session()->put('url.intended', route('download-links.show', $downloadLink));

        return redirect()->route('login');
    }

    /**
     * Guests must not receive the external address while login is required.
     */
    public static function exposeExternalUrl(): bool
    {
        return ! Setting::requireLoginToDownload() || auth()->check();
    }
}
