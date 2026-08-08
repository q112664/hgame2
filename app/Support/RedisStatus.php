<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisStatus
{
    public static function isConfigured(): bool
    {
        return config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }

    /**
     * @return array{
     *     configured: bool,
     *     connected: bool,
     *     error: string|null,
     *     latencyMs: float|null,
     *     client: string,
     *     host: string,
     *     port: int|string,
     *     drivers: array{cache: string, queue: string, session: string},
     *     server: array<string, string|int|null>,
     *     memory: array<string, string|int|null>,
     *     stats: array<string, string|int|null>
     * }
     */
    public static function snapshot(): array
    {
        $drivers = [
            'cache' => (string) config('cache.default'),
            'queue' => (string) config('queue.default'),
            'session' => (string) config('session.driver'),
        ];

        $base = [
            'configured' => static::isConfigured(),
            'connected' => false,
            'error' => null,
            'latencyMs' => null,
            'client' => (string) config('database.redis.client', 'phpredis'),
            'host' => (string) config('database.redis.default.host', '127.0.0.1'),
            'port' => config('database.redis.default.port', 6379),
            'drivers' => $drivers,
            'server' => [],
            'memory' => [],
            'stats' => [],
        ];

        if (! static::isConfigured()) {
            $base['error'] = 'Redis is not selected for cache, queue, or session.';

            return $base;
        }

        try {
            $connection = Redis::connection();
            $started = hrtime(true);
            $pong = $connection->ping();
            $latencyMs = round((hrtime(true) - $started) / 1_000_000, 2);

            $ok = $pong === true
                || $pong === 'PONG'
                || $pong === '+PONG'
                || (is_object($pong) && method_exists($pong, '__toString') && str_contains((string) $pong, 'PONG'));

            if (! $ok) {
                $base['error'] = 'Unexpected PING response.';

                return $base;
            }

            /** @var array<string, mixed> $info */
            $info = $connection->info();

            $base['connected'] = true;
            $base['latencyMs'] = $latencyMs;
            $base['server'] = [
                'redis_version' => self::infoValue($info, 'redis_version'),
                'uptime_in_seconds' => self::infoValue($info, 'uptime_in_seconds'),
                'uptime_in_days' => self::infoValue($info, 'uptime_in_days'),
                'role' => self::infoValue($info, 'role'),
                'os' => self::infoValue($info, 'os'),
            ];
            $base['memory'] = [
                'used_memory_human' => self::infoValue($info, 'used_memory_human'),
                'used_memory_peak_human' => self::infoValue($info, 'used_memory_peak_human'),
                'maxmemory_human' => self::infoValue($info, 'maxmemory_human'),
            ];
            $base['stats'] = [
                'connected_clients' => self::infoValue($info, 'connected_clients'),
                'total_connections_received' => self::infoValue($info, 'total_connections_received'),
                'total_commands_processed' => self::infoValue($info, 'total_commands_processed'),
                'instantaneous_ops_per_sec' => self::infoValue($info, 'instantaneous_ops_per_sec'),
                'keyspace_hits' => self::infoValue($info, 'keyspace_hits'),
                'keyspace_misses' => self::infoValue($info, 'keyspace_misses'),
                'db0' => self::infoValue($info, 'db0'),
            ];
        } catch (Throwable $e) {
            $base['error'] = $e->getMessage();
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private static function infoValue(array $info, string $key): string|int|null
    {
        if (array_key_exists($key, $info)) {
            return self::normalizeInfoValue($info[$key]);
        }

        // phpredis may nest sections (Server, Memory, Stats, …).
        foreach ($info as $section) {
            if (! is_array($section)) {
                continue;
            }

            if (array_key_exists($key, $section)) {
                return self::normalizeInfoValue($section[$key]);
            }
        }

        return null;
    }

    private static function normalizeInfoValue(mixed $value): string|int|null
    {
        if (is_string($value) || is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return is_float($value) ? (string) $value : null;
    }
}
