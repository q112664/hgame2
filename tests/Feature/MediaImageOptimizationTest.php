<?php

use App\Jobs\ProcessMediaOperationItem;
use App\Models\Game;
use App\Models\GameScreenshot;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Support\MediaImageOptimizer;
use App\Support\MediaReferenceRewriter;
use App\Support\MediaStorageManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
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
