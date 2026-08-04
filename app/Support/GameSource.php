<?php

namespace App\Support;

/**
 * Helpers for external game storefronts (DLsite, Steam, etc.).
 */
class GameSource
{
    /**
     * Local public assets for known storefronts (name lowercased → public path).
     *
     * @var array<string, string>
     */
    private const FAVICONS = [
        'dlsite' => '/images/sources/dlsite.ico',
        'dl site' => '/images/sources/dlsite.ico',
        'steam' => '/images/sources/steam.ico',
    ];

    /**
     * Hosts that map to a local favicon asset (substring match on product URL host).
     *
     * @var array<string, string>
     */
    private const HOST_FAVICONS = [
        'dlsite.com' => 'dlsite',
        'steampowered.com' => 'steam',
        'steamcommunity.com' => 'steam',
    ];

    /**
     * Fallback hosts for Google s2 favicons when no official icon is known.
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
     * @return list<array{name: string, slug: string, favicon_url: string}>
     */
    public static function known(): array
    {
        return [
            [
                'name' => 'DLsite',
                'slug' => 'dlsite',
                'favicon_url' => self::FAVICONS['dlsite'],
            ],
            [
                'name' => 'Steam',
                'slug' => 'steam',
                'favicon_url' => self::FAVICONS['steam'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::known())
            ->mapWithKeys(fn (array $source): array => [$source['name'] => $source['name']])
            ->all();
    }

    public static function faviconUrl(?string $name, ?string $url = null): ?string
    {
        if (filled($name)) {
            $key = strtolower(trim((string) $name));

            if (isset(self::FAVICONS[$key])) {
                return self::FAVICONS[$key];
            }
        }

        if (filled($url)) {
            $host = parse_url((string) $url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $host = strtolower($host);

                foreach (self::HOST_FAVICONS as $needle => $faviconKey) {
                    if (str_contains($host, $needle)) {
                        return self::FAVICONS[$faviconKey];
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
}
