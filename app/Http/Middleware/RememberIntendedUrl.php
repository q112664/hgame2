<?php

namespace App\Http\Middleware;

use App\Support\IntendedUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberIntendedUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('post') &&
            in_array($request->route()?->getName(), ['login.store', 'register.store'], true) &&
            is_string($request->input('redirect')) &&
            $request->input('redirect') !== ''
        ) {
            IntendedUrl::remember($request, overwrite: true);
        }

        return $next($request);
    }
}
