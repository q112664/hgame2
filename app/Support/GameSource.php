<?php

namespace App\Support;

use App\Models\ResourceSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers for external game storefronts (DLsite, Steam, custom library entries).
 */
class GameSource
{
    private const CACHE_KEY = 'resource_sources.catalog';

    /**
     * Fallback hosts for Google s2 favicons when no library icon is known.
     *
     * @var array<string, string>
     */
    private const DOMAINS = [
        'dlsite' => 'www.dlsite.com',
        'dl site' => 'www.dlsite.com',
        'booth' => 'booth.pm',
        'fantia' => 'fantia.jp',
        'fanza' => 'www.dmm.co.jp',
        'dmm' => 'www.dmm.co.jp',
        'ci-en' => 'ci-en.dlsite.com',
        'cien' => 'ci-en.dlsite.com',
        'patreon' => 'www.patreon.com',
        'steam' => 'store.steampowered.com',
        'gumroad' => 'gumroad.com',
        'itch.io' => 'itch.io',
        'itch' => 'itch.io',
    ];

    /**
     * Storefronts offered in admin UI and the publish API taxonomies list.
     *
     * @return list<array{name: string, slug: string, favicon_url: string|null}>
     */
    public static function known(): array
    {
        return array_values(array_map(
            static fn (array $source): array => [
                'name' => $source['name'],
                'slug' => $source['slug'],
                'favicon_url' => $source['favicon_url'],
            ],
            self::catalog(),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::catalog())
            ->mapWithKeys(fn (array $source): array => [$source['name'] => $source['name']])
            ->all();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function faviconUrl(?string $name, ?string $url = null): ?string
    {
        $catalog = self::catalog();

        if (filled($name)) {
            $key = strtolower(trim((string) $name));

            foreach ($catalog as $source) {
                if ($source['name_key'] === $key || $source['slug'] === $key) {
                    if ($source['favicon_url'] !== null) {
                        return $source['favicon_url'];
                    }

                    break;
                }
            }
        }

        if (filled($url)) {
            $host = parse_url((string) $url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $host = strtolower($host);

                foreach ($catalog as $source) {
                    if (
                        $source['host_hint'] !== null
                        && str_contains($host, $source['host_hint'])
                        && $source['favicon_url'] !== null
                    ) {
                        return $source['favicon_url'];
                    }
                }
            }
        }

        $host = static::hostFor($name, $url);

        if ($host === null) {
            return null;
        }

        return 'https://www.google.com/s2/favicons?domain='.rawurlencode($host).'&sz=32';
    }

    public static function hostFor(?string $name, ?string $url = null): ?string
    {
        if (filled($url)) {
            $host = parse_url((string) $url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        if (! filled($name)) {
            return null;
        }

        $key = strtolower(trim((string) $name));

        foreach (self::catalog() as $source) {
            if (
                ($source['name_key'] === $key || $source['slug'] === $key)
                && $source['host_hint'] !== null
            ) {
                return $source['host_hint'];
            }
        }

        return self::DOMAINS[$key] ?? null;
    }

    /**
     * Present source fields for the public resource hero.
     *
     * @return array{name: string|null, id: string|null, url: string|null, faviconUrl: string|null}|null
     */
    public static function present(?string $name, ?string $id, ?string $url): ?array
    {
        $name = filled($name) ? trim((string) $name) : null;
        $id = filled($id) ? trim((string) $id) : null;
        $url = filled($url) ? trim((string) $url) : null;

        if ($name === null && $id === null && $url === null) {
            return null;
        }

        return [
            'name' => $name,
            'id' => $id,
            'url' => $url,
            'faviconUrl' => static::faviconUrl($name, $url),
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     name_key: string,
     *     slug: string,
     *     favicon_url: string|null,
     *     host_hint: string|null
     * }>
     */
    private static function catalog(): array
    {
        if (! self::tableReady()) {
            return [];
        }

        /** @var list<array{name: string, name_key: string, slug: string, favicon_url: string|null, host_hint: string|null}> */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return ResourceSource::query()
                ->ordered()
                ->get(['name', 'slug', 'icon_path', 'host_hint'])
                ->map(static function (ResourceSource $source): array {
                    $hint = filled($source->host_hint)
                        ? strtolower(trim((string) $source->host_hint))
                        : null;

                    return [
                        'name' => $source->name,
                        'name_key' => strtolower(trim($source->name)),
                        'slug' => $source->slug,
                        'favicon_url' => $source->iconUrl(),
                        'host_hint' => $hint !== '' ? $hint : null,
                    ];
                })
                ->all();
        });
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable((new ResourceSource)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }
}
