<?php

use App\Actions\Media\GenerateCoverThumbnails;
use App\Models\Game;
use App\Support\GamePresenter;
use App\Support\Media;
use App\Support\MediaThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(Media::diskName());
});

test('it generates a webp thumbnail at the default max width for wide cover images', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 800)
        ->store('games/covers', Media::diskName());

    $thumbnailPath = MediaThumbnail::generate($path);

    expect($thumbnailPath)->toBe(MediaThumbnail::pathFor($path))
        ->and(Media::disk()->exists($thumbnailPath))->toBeTrue();

    $binary = Media::disk()->get($thumbnailPath);
    $size = getimagesizefromstring($binary);

    expect($size[0])->toBe(MediaThumbnail::maxWidth())
        ->and($size[1])->toBe(350)
        ->and($size['mime'])->toBe('image/webp');
});

test('it materializes a webp thumbnail even when the cover is already small enough', function () {
    $path = UploadedFile::fake()
        ->image('small.jpg', 400, 250)
        ->store('games/covers', Media::diskName());

    $thumbnailPath = MediaThumbnail::generate($path);

    expect($thumbnailPath)->toBe(MediaThumbnail::pathFor($path))
        ->and(Media::disk()->exists($thumbnailPath))->toBeTrue();

    $size = getimagesizefromstring(Media::disk()->get($thumbnailPath));

    expect($size[0])->toBe(400)
        ->and($size[1])->toBe(250)
        ->and($size['mime'])->toBe('image/webp');
});

test('card thumbnail urls use the deterministic thumbnail path without requiring objects to exist', function () {
    $path = 'games/covers/example-cover.jpg';

    expect(Media::disk()->exists($path))->toBeFalse()
        ->and(Media::disk()->exists(MediaThumbnail::pathFor($path)))->toBeFalse();

    // Previously this probed the disk (and could generate on the request path).
    // With object storage that is one HTTP round-trip per card and stalls the homepage.
    expect(MediaThumbnail::url($path))
        ->toContain('thumbs/example-cover.webp')
        ->not->toContain('example-cover.jpg');
});

test('saving a game with a cover generates a card thumbnail', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', Media::diskName());

    $game = Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]);

    $thumbnailPath = MediaThumbnail::pathFor($path);

    expect(Media::disk()->exists($thumbnailPath))->toBeTrue();

    $card = GamePresenter::card($game->fresh(['category', 'tags', 'releases']));
    $detail = GamePresenter::detail($game->fresh([
        'category',
        'tags',
        'screenshots',
        'releases',
    ]));

    expect($card['thumbnail'])->toContain('/thumbs/')
        ->and($detail['thumbnail'])->toContain('/thumbs/')
        ->and($detail['cover'])->not->toContain('/thumbs/')
        ->and($detail['cover'])->toContain($path);
});

test('deleting a game removes its cover thumbnail', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', Media::diskName());

    $game = Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]);

    $thumbnailPath = MediaThumbnail::pathFor($path);

    expect(Media::disk()->exists($thumbnailPath))->toBeTrue();

    $game->delete();

    expect(Media::disk()->exists($path))->toBeFalse()
        ->and(Media::disk()->exists($thumbnailPath))->toBeFalse();
});

test('the generate cover thumbnails action backfills missing thumbs', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1600, 1000)
        ->store('games/covers', Media::diskName());

    // Create without firing model events so the action is the only generator.
    $game = Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]));

    $thumbnailPath = MediaThumbnail::pathFor($path);

    expect(Media::disk()->exists($thumbnailPath))->toBeFalse();

    $result = app(GenerateCoverThumbnails::class)();

    expect($result['generated'])->toBe(1)
        ->and(Media::disk()->exists($thumbnailPath))->toBeTrue()
        ->and($game->fresh())->not->toBeNull();
});

test('the generate cover thumbnails command delegates to the action', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1600, 1000)
        ->store('games/covers', Media::diskName());

    Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]));

    Artisan::call('media:generate-cover-thumbnails');

    expect(Media::disk()->exists(MediaThumbnail::pathFor($path)))->toBeTrue();
});
