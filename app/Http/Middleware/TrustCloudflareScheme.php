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
 * When the app is configured for https, mark the request as secure and drop the
 * forwarded port, so URL generation and signature validation agree again.
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
            $request->server->set('SERVER_PORT', '443');
            $request->headers->set('X-Forwarded-Proto', 'https');
            // Cloudflare forwards the origin hop's port (80). Left in place, every
            // generated url comes out as https://host:80/..., which no signature
            // signed over https://host/... can ever match.
            $request->headers->remove('X-Forwarded-Port');
        }

        return $next($request);
    }
}
