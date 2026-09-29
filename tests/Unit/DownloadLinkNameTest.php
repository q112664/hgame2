<?php

use App\Support\DownloadLinkName;

test('a link is named after the site it points at', function (string $url, string $expected) {
    expect(DownloadLinkName::forUrl($url))->toBe($expected);
})->with([
    'the subdomain that prompted this' => ['https://dl.nekobox.club/file.zip', 'Nekobox'],
    'a host pasted without a scheme' => ['dl.nekobox.club/file.zip', 'Nekobox'],
    'a bare host' => ['dl.nekobox.club', 'Nekobox'],
    'uppercase host' => ['https://NEKOBOX.CLUB/game.zip', 'Nekobox'],
    'www is not part of a name' => ['https://www.mediafire.com/file/abc', 'Mediafire'],
    'neither is a service subdomain' => ['https://pan.baidu.com/s/xyz', 'Baidu'],
    'a single-label country domain' => ['https://mega.nz/file/abc', 'Mega'],
    'the brand sits above the subdomain' => ['https://drive.google.com/file/d/abc', 'Google'],
    'a two-letter second level' => ['https://gofile.io/d/abc', 'Gofile'],
    'a leading digit keeps its place' => ['https://1fichier.com/?abc', '1fichier'],
    'a hyphenated name' => ['https://my-files.com/game.zip', 'My Files'],
    'a hyphenated subdomain is still dropped' => ['https://my-files.example.com/game.zip', 'Example'],
    'a compound suffix' => ['https://example.co.uk/game.zip', 'Example'],
    'a compound suffix under a subdomain' => ['https://dl.example.com.cn/game.zip', 'Example'],
    'a port is not part of a name' => ['https://dl.nekobox.club:8443/file.zip', 'Nekobox'],
    'localhost is its own name' => ['http://localhost:8000/file.zip', 'Localhost'],
    'an address has no name to shorten' => ['http://192.168.1.10/file.zip', '192.168.1.10'],
    'nothing to name' => ['', 'Download'],
    'only a path' => ['/files/game.zip', 'Download'],
    'not a url at all' => ['not a url', 'Download'],
    'a scheme with no host' => ['https://', 'Download'],
]);

test('a name a person chose is never replaced', function () {
    expect(DownloadLinkName::resolve('Baidu Netdisk', 'https://pan.baidu.com/s/xyz'))->toBe('Baidu Netdisk')
        ->and(DownloadLinkName::resolve('My own mirror', 'https://dl.nekobox.club/x'))->toBe('My own mirror')
        // Even when the URL cannot be read, a chosen name stands.
        ->and(DownloadLinkName::resolve('Baidu Netdisk', 'not a url'))->toBe('Baidu Netdisk');
});

test('a name that is only the old host is shortened', function () {
    expect(DownloadLinkName::resolve('dl.nekobox.club', 'https://dl.nekobox.club/x'))->toBe('Nekobox')
        ->and(DownloadLinkName::resolve('DL.NEKOBOX.CLUB', 'https://dl.nekobox.club/x'))->toBe('Nekobox')
        // The placeholder from before this class existed is not a decision
        // anyone made either, so a readable URL replaces it.
        ->and(DownloadLinkName::resolve('Download', 'https://dl.nekobox.club/x'))->toBe('Nekobox');
});

test('a missing name is derived and an unreadable url still gets a name', function () {
    expect(DownloadLinkName::resolve(null, 'https://dl.nekobox.club/x'))->toBe('Nekobox')
        ->and(DownloadLinkName::resolve('', 'https://dl.nekobox.club/x'))->toBe('Nekobox')
        ->and(DownloadLinkName::resolve('   ', 'https://dl.nekobox.club/x'))->toBe('Nekobox')
        ->and(DownloadLinkName::resolve(null, 'not a url'))->toBe('Download')
        ->and(DownloadLinkName::resolve('Download', 'not a url'))->toBe('Download');
});

test('a host is read without its port or casing', function () {
    expect(DownloadLinkName::hostFor('https://dl.nekobox.club:8443/x'))->toBe('dl.nekobox.club')
        ->and(DownloadLinkName::hostFor('dl.nekobox.club/x'))->toBe('dl.nekobox.club')
        ->and(DownloadLinkName::hostFor('https://[::1]/x'))->toBe('::1')
        ->and(DownloadLinkName::hostFor('not a url'))->toBeNull()
        ->and(DownloadLinkName::hostFor(''))->toBeNull();
});
