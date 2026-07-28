<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Request;

/**
 * Protect the admin panel with the site session (Fortify / web guard).
 * Guests are sent to the public login page instead of Filament's own login UI.
 */
class AuthenticateFilamentPanel extends FilamentAuthenticate
{
    /**
     * @param  Request  $request
     */
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
