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
 * When Cloudflare says the visitor used https, mark the request as secure so URL
 * generation and signature validation agree again.
 */
class TrustCloudflareScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isSecure() && $request->headers->has('CF-Ray')) {
            /** @var array<string, mixed> $visitor */
            $visitor = json_decode((string) $request->headers->get('CF-Visitor'), true) ?: [];

            $visitorIsSecure = ($visitor['scheme'] ?? null) === 'https';
            $appIsSecure = str_starts_with((string) config('app.url'), 'https://');

            if ($visitorIsSecure || $appIsSecure) {
                $request->server->set('HTTPS', 'on');
                $request->headers->set('X-Forwarded-Proto', 'https');
            }
        }

        return $next($request);
    }
}
