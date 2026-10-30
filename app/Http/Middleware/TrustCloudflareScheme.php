<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare terminates TLS and, in Flexible mode, reaches the origin over plain
 * HTTP while sending `X-Forwarded-Proto: http`. Laravel then believes the request
 * is http even though the reader is on https, which breaks every signed URL:
 * URLs are generated from APP_URL (https) but validated against the http request,
 * so the HMAC never matches (Livewire uploads answer 401, verification links fail).
 *
 * When the app is configured for https, mark the request as secure so URL generation
 * and signature validation agree again.
 */
class TrustCloudflareScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        // The site is https-only when APP_URL says so, so an http request can only be
        // the proxy hop. Trusting the header alone is not enough: Cloudflare sends
        // `X-Forwarded-Proto: http` in Flexible mode and may drop CF-Visitor.
        if (! $request->isSecure() && str_starts_with((string) config('app.url'), 'https://')) {
            $request->server->set('HTTPS', 'on');
            $request->headers->set('X-Forwarded-Proto', 'https');
        }

        return $next($request);
    }
}
