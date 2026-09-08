<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('assigning only a release contributor does not bump downloads_updated_at', function () {
    $game = Game::factory()->create([
        'downloads_updated_at' => null,
    ]);
    $release = GameRelease::factory()->for($game)->create([
        'user_id' => null,
        'title' => 'Windows package',
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $frozen = now()->subDay()->startOfSecond();
    $game->forceFill(['downloads_updated_at' => $frozen])->saveQuietly();

    $user = User::factory()->create(['email' => 'mirrorfox@example.com']);

    $release->update(['user_id' => $user->id]);

    expect($release->fresh()->user_id)->toBe($user->id)
        ->and($game->fresh()->downloads_updated_at?->equalTo($frozen))->toBeTrue();
});

test('editing release notes version size or download url does not bump downloads_updated_at', function () {
    $game = Game::factory()->create([
        'downloads_updated_at' => null,
    ]);
    $release = GameRelease::factory()->for($game)->create([
        'title' => 'Windows package',
        'version' => '1.0',
        'file_size' => '1 GB',
        'description' => '<p>Notes</p>',
    ]);
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://example.com/old.zip',
    ]);

    expect($game->fresh()->downloads_updated_at)->toBeNull();

    $release->update([
        'title' => 'Windows package v2',
        'version' => '2.0',
        'file_size' => '2 GB',
        'description' => '<p>MD5: abc</p>',
    ]);
    $link->update(['url' => 'https://example.com/new.zip']);
    $release->delete();

    expect($game->fresh()->downloads_updated_at)->toBeNull();
});

test('artisan command bulk assigns contributor without touching downloads_updated_at', function () {
    Carbon::setTestNow(now()->startOfSecond());

    $user = User::factory()->create([
        'email' => 'bulk@example.com',
    ]);
    $game = Game::factory()->create([
        'slug' => 'bulk-game',
    ]);

    $missing = GameRelease::factory()->for($game)->create(['user_id' => null]);
    $already = GameRelease::factory()->for($game)->create([
        'user_id' => User::factory()->create()->id,
    ]);

    $frozen = now()->subDays(3)->startOfSecond();
    $game->forceFill(['downloads_updated_at' => $frozen])->saveQuietly();

    $this->artisan('releases:assign-contributor', [
        'email' => 'Bulk@example.com',
        '--game' => ['bulk-game'],
        '--only-missing' => true,
    ])->assertSuccessful();

    expect($missing->fresh()->user_id)->toBe($user->id)
        ->and($already->fresh()->user_id)->not->toBe($user->id)
        ->and($game->fresh()->downloads_updated_at?->equalTo($frozen))->toBeTrue();

    Carbon::setTestNow();
});

test('artisan command dry run does not write changes', function () {
    $user = User::factory()->create(['email' => 'dry@example.com']);
    $release = GameRelease::factory()->create(['user_id' => null]);

    $this->artisan('releases:assign-contributor', [
        'email' => 'dry@example.com',
        '--only-missing' => true,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect($release->fresh()->user_id)->toBeNull()
        ->and($user->id)->not->toBeNull();
});
