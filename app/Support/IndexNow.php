<?php

namespace App\Support;

use App\Jobs\SubmitIndexNowUrls;
use App\Models\Game;
use App\Models\Setting;

final class IndexNow
{
    public const string Endpoint = 'https://api.indexnow.org/indexnow';

    public static function enabled(): bool
    {
        return Setting::boolean('indexnow_enabled', false)
            && self::normalizedKey() !== null;
    }

    public static function normalizedKey(): ?string
    {
        $key = trim((string) (Setting::get('indexnow_key') ?? ''));

        if (! preg_match('/^[A-Za-z0-9-]{8,128}$/', $key)) {
            return null;
        }

        return $key;
    }

    public static function keyFileUrl(): ?string
    {
        $key = self::normalizedKey();

        if ($key === null) {
            return null;
        }

        return rtrim(Setting::siteUrl(), '/').'/'.$key.'.txt';
    }

    public static function host(): ?string
    {
        $host = parse_url(Setting::siteUrl(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    public static function gameUrl(Game $game): string
    {
        return rtrim(Setting::siteUrl(), '/').'/games/'.$game->slug;
    }

    /**
     * @param  list<string>  $urls
     */
    public static function submit(array $urls): void
    {
        if (! self::enabled()) {
            return;
        }

        $urls = array_values(array_unique(array_filter(
            $urls,
            fn (string $url): bool => $url !== '',
        )));

        if ($urls === []) {
            return;
        }

        $pending = SubmitIndexNowUrls::dispatch($urls);

        if (! app()->runningUnitTests()) {
            $pending->afterCommit();
        }
    }

    public static function submitGame(Game $game): void
    {
        self::submit([self::gameUrl($game)]);
    }
}
