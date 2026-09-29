<?php

namespace App\Support;

/**
 * Names a download link after where it points, so several mirrors of the same
 * file can be told apart: `dl.nekobox.club` becomes `Nekobox`.
 *
 * The `label` column is not editable in the admin, so this is the only thing
 * that names a link. That is also why `resolve()` refuses to overwrite an
 * existing name unless it is recognisably one this class wrote itself.
 */
final class DownloadLinkName
{
    public const FALLBACK = 'Download';

    /**
     * Suffixes that need two labels before the registrable name, so that
     * `example.co.uk` yields `Example` instead of `Co`.
     *
     * @var list<string>
     */
    private const MULTI_PART_SUFFIXES = [
        'ac.uk', 'co.id', 'co.il', 'co.in', 'co.jp', 'co.kr', 'co.nz', 'co.th',
        'co.uk', 'co.za', 'com.ar', 'com.au', 'com.br', 'com.cn', 'com.hk',
        'com.mx', 'com.my', 'com.ph', 'com.sg', 'com.tr', 'com.tw', 'com.ua',
        'com.vn', 'gov.cn', 'gov.uk', 'net.au', 'net.cn', 'org.cn', 'org.uk',
    ];

    /**
     * The name to store for a link, keeping a name a person already gave it.
     */
    public static function resolve(?string $current, string $url): string
    {
        $current = trim((string) $current);

        // Empty, or the placeholder stored when the URL could not be read at
        // all. Neither is a decision anyone made, so both are fair to replace.
        if ($current === '' || strcasecmp($current, self::FALLBACK) === 0) {
            return self::forUrl($url);
        }

        $host = self::hostFor($url);

        // A name equal to the host is one an earlier version of this class
        // wrote, before it learned to shorten them, so it is safe to upgrade.
        // Anything else was chosen deliberately and is left alone.
        if ($host !== null && strcasecmp($current, $host) === 0) {
            return self::forUrl($url);
        }

        return $current;
    }

    /**
     * The registrable name of a URL's host, title-cased, or `Download` when the
     * URL names no host at all.
     */
    public static function forUrl(string $url): string
    {
        $host = self::hostFor($url);

        if ($host === null) {
            return self::FALLBACK;
        }

        // An address is already as specific as it gets; shortening it would
        // invent a name that does not exist.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $labels = explode('.', $host);
        $labelCount = count($labels);

        if ($labelCount === 1) {
            return self::prettify($labels[0]) ?: $host;
        }

        $index = $labelCount - (self::hasMultiPartSuffix($labels) ? 3 : 2);

        return self::prettify($labels[max($index, 0)]) ?: $host;
    }

    /**
     * The host of a URL, lowercased and without its port, or null when there is
     * no host to find.
     */
    public static function hostFor(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // People paste `dl.nekobox.club/file.zip` with no scheme, which
        // parse_url reads as a bare path; a protocol-relative retry still finds
        // the host. A URL that does have a scheme and still has no host is
        // simply broken, and the retry would mistake the scheme for the host.
        if ((! is_string($host) || $host === '') && ! str_contains($url, '://')) {
            $host = parse_url('//'.$url, PHP_URL_HOST);
        }

        if (! is_string($host) || $host === '') {
            return null;
        }

        // parse_url keeps the brackets on an IPv6 literal; they are not part of
        // the address.
        $host = strtolower(trim(trim($host), '[].'));

        // parse_url is lenient enough to call "not a url" a host. Whitespace
        // never appears in a real hostname, so this is paste damage rather than
        // a domain, and a name made from it would be nonsense.
        if ($host === '' || preg_match('/\s/', $host) === 1) {
            return null;
        }

        return $host;
    }

    /**
     * @param  list<string>  $labels
     */
    private static function hasMultiPartSuffix(array $labels): bool
    {
        $labelCount = count($labels);

        if ($labelCount < 3) {
            return false;
        }

        return in_array(
            $labels[$labelCount - 2].'.'.$labels[$labelCount - 1],
            self::MULTI_PART_SUFFIXES,
            true,
        );
    }

    /**
     * ucfirst rather than a title-case call, so `1fichier` keeps its digit and
     * does not turn into `1Fichier`.
     */
    private static function prettify(string $label): string
    {
        $words = preg_split('/[-_]+/', $label, flags: PREG_SPLIT_NO_EMPTY);

        if (! is_array($words) || $words === []) {
            return '';
        }

        return implode(' ', array_map(ucfirst(...), $words));
    }
}
