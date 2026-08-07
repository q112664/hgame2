<?php

use App\Actions\Media\GenerateCoverThumbnails;
use App\Jobs\GenerateCoverThumbnail;
use App\Models\Game;
use App\Support\GamePresenter;
use App\Support\Media;
use App\Support\MediaThumbnail;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filesystems.media' => 'public']);
    Storage::fake('public');
    Storage::fake('r2');
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

test('it materializes a webp thumbnail when the cover is already small enough', function (): void {
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

test('r2 thumbnails keep an identical local rollback copy', function (): void {
    config(['filesystems.media' => 'r2']);
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', 'r2');

    $thumbnailPath = MediaThumbnail::generate($path);

    expect($thumbnailPath)->toBe(MediaThumbnail::pathFor($path))
        ->and(Storage::disk('r2')->exists($thumbnailPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($thumbnailPath))->toBeTrue()
        ->and(Storage::disk('r2')->get($thumbnailPath))
        ->toBe(Storage::disk('public')->get($thumbnailPath));
});

test('r2 thumbnail backfill repairs a missing local copy without regeneration', function (): void {
    config(['filesystems.media' => 'r2']);
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', 'r2');
    $thumbnailPath = MediaThumbnail::generate($path);
    $r2Binary = Storage::disk('r2')->get($thumbnailPath);
    Storage::disk('public')->delete($thumbnailPath);

    Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]));

    $result = app(GenerateCoverThumbnails::class)();

    expect($result['generated'])->toBe(1)
        ->and(Storage::disk('public')->get($thumbnailPath))->toBe($r2Binary);
});

test('r2 thumbnail writes restore the previous remote copy when local mirroring fails', function (): void {
    config(['filesystems.media' => 'r2']);
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', 'r2');
    $thumbnailPath = MediaThumbnail::pathFor($path);
    Storage::disk('r2')->put($thumbnailPath, 'previous-thumbnail');
    $r2 = Storage::disk('r2');
    $public = Mockery::mock(FilesystemContract::class);
    $public->shouldReceive('exists')->with($thumbnailPath)->andReturn(false);
    $public->shouldReceive('put')->with($thumbnailPath, Mockery::type('string'), 'public')->andReturn(false);

    Storage::shouldReceive('disk')->with('r2')->andReturn($r2);
    Storage::shouldReceive('disk')->with('public')->andReturn($public);

    expect(MediaThumbnail::generate($path))->toBeNull()
        ->and($r2->get($thumbnailPath))->toBe('previous-thumbnail');
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

test('cover thumbnail jobs ignore a stale cover path', function (): void {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', Media::diskName());
    $game = Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]));
    $stalePath = 'games/covers/replaced.jpg';

    (new GenerateCoverThumbnail((int) $game->getKey(), $stalePath))->handle();

    expect(Media::disk()->exists(MediaThumbnail::pathFor($stalePath)))->toBeFalse();
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

test('the generate cover thumbnails action reports missing managed originals as failures', function (): void {
    Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => 'games/covers/missing.jpg',
        'cover_url' => '',
    ]));

    expect(app(GenerateCoverThumbnails::class)())
        ->toMatchArray([
            'generated' => 0,
            'skipped' => 0,
            'failed' => 1,
        ]);
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
