<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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

        if (str_contains($request->path(), 'upload-file')) {
            $query = collect($request->query())->except('signature')->all();
            $base = '/'.$request->getPathInfo();
            Log::info('upload-debug', [
                'url' => $request->url(),
                'secure' => $request->isSecure(),
                'app_url' => config('app.url'),
                'sig' => substr((string) $request->query('signature'), 0, 16),
                'expected_https' => hash_hmac('sha256', 'https://eroga.me'.$base.'?'.Arr::query($query), (string) config('app.key')),
                'expected_http' => hash_hmac('sha256', 'http://eroga.me'.$base.'?'.Arr::query($query), (string) config('app.key')),
                'ray' => $request->header('CF-Ray'),
                'cfv' => $request->header('CF-Visitor'),
                'xfp' => $request->header('X-Forwarded-Proto'),
                'host' => $request->getHost(),
                'valid' => $request->hasValidSignature(),
            ]);
        }

        return $next($request);
    }
}
