<?php

use App\Actions\Games\RelabelAutoDerivedDownloadLinks;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Create a link in the state the old naming left behind, without letting the
 * saving hook rewrite the label first.
 */
function legacyNamedLink(GameRelease $release, string $url, string $label, bool $isActive = true): GameDownloadLink
{
    return GameDownloadLink::withoutEvents(fn (): GameDownloadLink => GameDownloadLink::create([
        'game_release_id' => $release->id,
        'label' => $label,
        'url' => $url,
        'is_active' => $isActive,
        'sort_order' => 0,
    ]));
}

test('a new link is named after the site it points at', function () {
    $release = GameRelease::factory()->create();

    $link = $release->downloadLinks()->create(['url' => 'https://dl.nekobox.club/game.zip']);

    expect($link->label)->toBe('Nekobox')
        ->and($link->is_active)->toBeTrue();
});

test('a name a person chose survives a later save', function () {
    $release = GameRelease::factory()->create();

    $link = $release->downloadLinks()->create([
        'label' => 'Baidu Netdisk',
        'url' => 'https://pan.baidu.com/s/xyz',
    ]);

    $link->url = 'https://pan.baidu.com/s/moved';
    $link->save();

    expect($link->fresh()->label)->toBe('Baidu Netdisk');
});

test('a link still named after its host is shortened when it is next saved', function () {
    $release = GameRelease::factory()->create();
    $link = legacyNamedLink($release, 'https://dl.nekobox.club/game.zip', 'dl.nekobox.club');

    $link->save();

    expect($link->fresh()->label)->toBe('Nekobox');
});

test('the backfill shortens host names and leaves chosen names alone', function () {
    $release = GameRelease::factory()->create();

    $legacy = legacyNamedLink($release, 'https://dl.nekobox.club/a.zip', 'dl.nekobox.club');
    $placeholder = legacyNamedLink($release, 'https://mega.nz/file/c', 'Download');
    $chosen = legacyNamedLink($release, 'https://pan.baidu.com/s/b', 'Baidu Netdisk');
    $alreadyShort = legacyNamedLink($release, 'https://gofile.io/d/d', 'Gofile');
    $unreadable = legacyNamedLink($release, 'not a url', 'Download');

    expect(app(RelabelAutoDerivedDownloadLinks::class)())->toBe(2)
        ->and($legacy->fresh()->label)->toBe('Nekobox')
        ->and($placeholder->fresh()->label)->toBe('Mega')
        ->and($chosen->fresh()->label)->toBe('Baidu Netdisk')
        ->and($alreadyShort->fresh()->label)->toBe('Gofile')
        ->and($unreadable->fresh()->label)->toBe('Download');
});

test('the backfill does not put switched off links back on the page', function () {
    $release = GameRelease::factory()->create();
    $link = legacyNamedLink($release, 'https://dl.nekobox.club/a.zip', 'dl.nekobox.club', isActive: false);

    app(RelabelAutoDerivedDownloadLinks::class)();

    $fresh = $link->fresh();

    expect($fresh->label)->toBe('Nekobox')
        ->and($fresh->is_active)->toBeFalse();
});

test('the resource page names each mirror after its own domain', function () {
    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();
    $release->downloadLinks()->create(['url' => 'https://dl.nekobox.club/game.zip']);

    $this->get(route('resources.show', $game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.releases.0.downloadLinks.0.label', 'Nekobox')
        );
});
