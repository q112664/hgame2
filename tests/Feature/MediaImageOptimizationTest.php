<?php

use App\Jobs\ProcessMediaOperationItem;
use App\Models\Game;
use App\Models\GameScreenshot;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Support\MediaImageOptimizer;
use App\Support\MediaPathCollector;
use App\Support\MediaReferenceRewriter;
use App\Support\MediaStorageManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('r2');
    config(['filesystems.media' => 'public']);
});

test('optimizer converts jpeg to validated webp and limits screenshot dimensions', function (): void {
    Storage::disk('public')->put(
        'games/screenshots/large.jpg',
        makeJpegImage(2400, 1200),
    );

    $result = app(MediaImageOptimizer::class)->optimize('public', 'games/screenshots/large.jpg');
    $decoded = imagecreatefromstring($result['binary']);

    expect($decoded)->not->toBeFalse()
        ->and(imagesx($decoded))->toBe(1920)
        ->and(imagesy($decoded))->toBe(960)
        ->and($result['target_checksum'])->toBe(hash('sha256', $result['binary']));

    imagedestroy($decoded);
});

test('bulk optimization rewrites cover screenshot and embedded references while retaining originals', function (): void {
    Queue::fake();
    $cover = 'games/covers/cover.jpg';
    $screenshot = 'games/screenshots/shot.jpg';
    $content = 'games/content/content.jpg';

    Storage::disk('public')->put($cover, makeJpegImage(1800, 2400));
    Storage::disk('public')->put($screenshot, makeJpegImage(2400, 1200));
    Storage::disk('public')->put($content, makeJpegImage(1600, 1200));

    $game = Game::factory()->create([
        'cover_path' => $cover,
        'description' => '<p><img src="/storage/'.$content.'"></p>',
    ]);
    $shot = GameScreenshot::factory()->for($game)->create(['path' => $screenshot]);

    $operation = app(MediaStorageManager::class)->startOptimization();
    Queue::assertPushed(ProcessMediaOperationItem::class, 3);

    $operation->items()->orderBy('id')->each(function (MediaOperationItem $item): void {
        (new ProcessMediaOperationItem((int) $item->getKey()))->handle(
            app(MediaStorageManager::class),
            app(MediaImageOptimizer::class),
            app(MediaPathCollector::class),
            app(MediaReferenceRewriter::class),
        );
    });

    $optimizedCover = 'games/covers/cover.webp';
    $optimizedScreenshot = 'games/screenshots/shot.webp';
    $optimizedContent = 'games/content/content.webp';

    expect($operation->refresh()->status)->toBe(MediaOperation::StatusCompleted)
        ->and($game->refresh()->cover_path)->toBe($optimizedCover)
        ->and($shot->refresh()->path)->toBe($optimizedScreenshot)
        ->and($game->description)->toContain('/storage/'.$optimizedContent)
        ->and(Storage::disk('public')->exists($optimizedCover))->toBeTrue()
        ->and(Storage::disk('public')->exists($optimizedScreenshot))->toBeTrue()
        ->and(Storage::disk('public')->exists($optimizedContent))->toBeTrue()
        ->and(Storage::disk('public')->exists($cover))->toBeTrue()
        ->and(Storage::disk('public')->exists($screenshot))->toBeTrue()
        ->and(Storage::disk('public')->exists($content))->toBeTrue();
});

test('verified optimized originals can be permanently cleaned up', function (): void {
    Queue::fake();
    $source = 'games/covers/cleanup.jpg';
    Storage::disk('public')->put($source, makeJpegImage(1800, 1200));
    $game = Game::factory()->create(['cover_path' => $source]);
    $manager = app(MediaStorageManager::class);

    runImageOperation($manager->startOptimization());

    $optimized = 'games/covers/cleanup.webp';
    $preview = $manager->cleanupPreview();

    expect($game->refresh()->cover_path)->toBe($optimized)
        ->and($preview['files'])->toBe(1)
        ->and($preview['bytes'])->toBeGreaterThan(0);

    $cleanup = $manager->startCleanup();
    Queue::assertPushed(ProcessMediaOperationItem::class, 2);
    runImageOperation($cleanup);

    expect($cleanup->refresh()->status)->toBe(MediaOperation::StatusCompleted)
        ->and($cleanup->failed_items)->toBe(0)
        ->and($cleanup->total_source_bytes)->toBe($preview['bytes'])
        ->and($cleanup->total_target_bytes)->toBe(0)
        ->and(Storage::disk('public')->exists($source))->toBeFalse()
        ->and(Storage::disk('public')->exists($optimized))->toBeTrue()
        ->and($manager->cleanupPreview()['files'])->toBe(0);
});

test('cleanup keeps originals when the optimized file fails verification', function (): void {
    Queue::fake();
    $source = 'games/covers/tampered.jpg';
    Storage::disk('public')->put($source, makeJpegImage(1800, 1200));
    Game::factory()->create(['cover_path' => $source]);
    $manager = app(MediaStorageManager::class);

    runImageOperation($manager->startOptimization());
    Storage::disk('public')->put('games/covers/tampered.webp', 'tampered');

    $cleanup = $manager->startCleanup();
    $item = $cleanup->items()->firstOrFail();

    expect(fn () => runImageItem($item))->toThrow(RuntimeException::class, 'checksum verification');

    expect($item->refresh()->status)->toBe(MediaOperationItem::StatusFailed)
        ->and(Storage::disk('public')->exists($source))->toBeTrue();
});

test('cleanup keeps originals that become referenced again', function (): void {
    Queue::fake();
    $source = 'games/covers/referenced.jpg';
    Storage::disk('public')->put($source, makeJpegImage(1800, 1200));
    $game = Game::factory()->create(['cover_path' => $source]);
    $manager = app(MediaStorageManager::class);

    runImageOperation($manager->startOptimization());
    $game->refresh()->forceFill(['cover_path' => $source])->save();

    expect($game->refresh()->cover_path)->toBe($source)
        ->and(app(MediaPathCollector::class)->references())->toContain($source)
        ->and($manager->cleanupPreview()['files'])->toBe(0)
        ->and(fn () => $manager->startCleanup())->toThrow(RuntimeException::class, 'No completed image optimization')
        ->and(Storage::disk('public')->exists($source))->toBeTrue();
});

test('r2 cleanup verifies and removes both remote and local rollback originals', function (): void {
    Queue::fake();
    config(['filesystems.media' => 'r2']);
    $source = 'games/covers/r2-cleanup.jpg';
    $sourceBinary = makeJpegImage(1800, 1200);
    Storage::disk('r2')->put($source, $sourceBinary);
    Storage::disk('public')->put($source, $sourceBinary);
    Game::factory()->create(['cover_path' => $source]);
    $manager = app(MediaStorageManager::class);

    runImageOperation($manager->startOptimization());
    $cleanup = $manager->startCleanup();
    runImageOperation($cleanup);

    expect($cleanup->refresh()->status)->toBe(MediaOperation::StatusCompleted)
        ->and(Storage::disk('r2')->exists($source))->toBeFalse()
        ->and(Storage::disk('public')->exists($source))->toBeFalse()
        ->and(Storage::disk('r2')->exists('games/covers/r2-cleanup.webp'))->toBeTrue()
        ->and(Storage::disk('public')->exists('games/covers/r2-cleanup.webp'))->toBeTrue();
});

function runImageOperation(MediaOperation $operation): void
{
    $operation->items()->orderBy('id')->each(fn (MediaOperationItem $item): mixed => runImageItem($item));
}

function runImageItem(MediaOperationItem $item): void
{
    (new ProcessMediaOperationItem((int) $item->getKey()))->handle(
        app(MediaStorageManager::class),
        app(MediaImageOptimizer::class),
        app(MediaPathCollector::class),
        app(MediaReferenceRewriter::class),
    );
}

function makeJpegImage(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 238, 240, 245);
    $accent = imagecolorallocate($image, 24, 120, 180);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);
    imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), $accent);

    ob_start();
    imagejpeg($image, null, 95);
    $binary = ob_get_clean();
    imagedestroy($image);

    return is_string($binary) ? $binary : '';
}
