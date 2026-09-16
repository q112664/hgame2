<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntendedUrl
{
    /**
     * @var list<string>
     */
    private const BLOCKED_PATH_PREFIXES = [
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'two-factor-challenge',
        'email/verify',
        'user/confirm-password',
        'dashboard',
        'auth',
    ];

    public static function remember(Request $request, bool $overwrite = false): void
    {
        if (! $overwrite && $request->session()->has('url.intended')) {
            return;
        }

        $candidate = $request->query('redirect') ?? $request->input('redirect');

        if (! is_string($candidate) || $candidate === '') {
            $candidate = $request->headers->get('referer');
        }

        if (! is_string($candidate) || $candidate === '') {
            return;
        }

        $intended = self::sanitize($candidate);

        if ($intended === null) {
            return;
        }

        $request->session()->put('url.intended', $intended);
    }

    public static function sanitize(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $parts['host'] ?? null;

        if ($host !== null && $appHost !== null && ! hash_equals((string) $appHost, $host)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $relative = ltrim($path, '/');

        foreach (self::BLOCKED_PATH_PREFIXES as $prefix) {
            if ($relative === $prefix || Str::startsWith($relative, $prefix.'/')) {
                return null;
            }
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return url($path.$query).self::fragment($parts['fragment'] ?? null);
    }

    /**
     * Keep content anchors (`#downloads`, `#comments`, `#comment-9`) so a deep
     * link survives the login redirect, but drop anything else: the value is
     * attacker-controlled and is replayed as a redirect target.
     */
    private static function fragment(?string $fragment): string
    {
        if ($fragment === null || $fragment === '') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $fragment) === 1
            ? '#'.$fragment
            : '';
    }
}
