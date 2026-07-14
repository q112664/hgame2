<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class SafeRemoteUrl
{
    /**
     * Validate a remote URL and pin it to a single allowed IP for the connection.
     *
     * @return array{url: string, scheme: string, host: string, port: int, ip: string, curl_resolve: list<string>}
     *
     * @throws ValidationException
     */
    public static function pin(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw ValidationException::withMessages([
                'media' => "Media URL [{$url}] is invalid.",
            ]);
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'media' => "Media URL [{$url}] must use http or https.",
            ]);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([
                'media' => "Media URL [{$url}] must not include credentials.",
            ]);
        }

        $host = strtolower((string) $parts['host']);

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw ValidationException::withMessages([
                'media' => "Media URL [{$url}] points to a blocked host.",
            ]);
        }

        if (in_array($host, [
            'metadata.google.internal',
            'metadata',
            'instance-data',
        ], true) || str_ends_with($host, '.internal')) {
            throw ValidationException::withMessages([
                'media' => "Media URL [{$url}] points to a blocked host.",
            ]);
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        $ip = null;

        foreach (self::resolveIps($host) as $candidate) {
            if (! self::isBlockedIp($candidate)) {
                $ip = $candidate;
                break;
            }
        }

        if ($ip === null) {
            throw ValidationException::withMessages([
                'media' => "Media URL [{$url}] resolves to a blocked network address.",
            ]);
        }

        return [
            'url' => $url,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ip' => $ip,
            'curl_resolve' => [sprintf('%s:%d:%s', $host, $port, $ip)],
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function assert(string $url): void
    {
        self::pin($url);
    }

    /**
     * @return list<string>
     */
    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = gethostbynamel($host) ?: [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        $ips = array_values(array_unique(array_filter($ips)));

        if ($ips === []) {
            throw ValidationException::withMessages([
                'media' => "Media URL host [{$host}] could not be resolved.",
            ]);
        }

        return $ips;
    }

    private static function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) === false) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
