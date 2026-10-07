<?php

use App\Actions\Media\PruneVerifiedR2Originals;
use App\Models\Game;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('r2');
    config(['filesystems.media' => 'r2']);
});

test('dry run reports verified originals without deleting them', function (): void {
    [$jpg, $webp] = seedPrunableOriginal();

    $result = app(PruneVerifiedR2Originals::class)();

    expect($result['eligible'])->toBe(1)
        ->and($result['deletable'])->toBe(1)
        ->and($result['deleted'])->toBe(0)
        ->and($result['skipped'])->toBe(0)
        ->and(Storage::disk('r2')->exists($jpg))->toBeTrue()
        ->and(Storage::disk('r2')->exists($webp))->toBeTrue();
});

test('force deletes only the verified r2 original', function (): void {
    [$jpg, $webp] = seedPrunableOriginal();
    Storage::disk('public')->put($jpg, 'original-bytes');
    $failed = 'games/covers/only-copy.jpg';
    $mismatched = 'games/covers/mismatched.jpg';
    $mismatchedWebp = 'games/covers/mismatched.webp';
    $orphan = 'games/covers/orphan.webp';
    Storage::disk('r2')->put($failed, 'only-copy');
    Storage::disk('r2')->put($mismatched, 'changed-bytes');
    Storage::disk('r2')->put($mismatchedWebp, 'webp-bytes');
    Storage::disk('r2')->put($orphan, 'orphan-webp');
    Game::factory()->create(['cover_path' => $mismatchedWebp]);

    $operation = MediaOperation::query()->firstOrFail();
    $operation->items()->create([
        'path' => $failed,
        'path_hash' => hash('sha256', $failed),
        'target_path' => 'games/covers/only-copy.webp',
        'target_path_hash' => hash('sha256', 'games/covers/only-copy.webp'),
        'status' => MediaOperationItem::StatusFailed,
        'source_checksum' => hash('sha256', 'only-copy'),
        'target_checksum' => hash('sha256', 'missing-webp'),
    ]);
    $operation->items()->create([
        'path' => $mismatched,
        'path_hash' => hash('sha256', $mismatched),
        'target_path' => $mismatchedWebp,
        'target_path_hash' => hash('sha256', $mismatchedWebp),
        'status' => MediaOperationItem::StatusCompleted,
        'source_checksum' => hash('sha256', 'original-bytes'),
        'target_checksum' => hash('sha256', 'webp-bytes'),
        'source_size' => strlen('changed-bytes'),
    ]);

    $result = app(PruneVerifiedR2Originals::class)(force: true);

    expect($result['deleted'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($result['skip_reasons'])->toBe(['checksum-mismatch' => 1])
        ->and(Storage::disk('r2')->exists($jpg))->toBeFalse()
        ->and(Storage::disk('r2')->exists($webp))->toBeTrue()
        ->and(Storage::disk('public')->exists($jpg))->toBeTrue()
        ->and(Storage::disk('r2')->exists($failed))->toBeTrue()
        ->and(Storage::disk('r2')->exists($mismatched))->toBeTrue()
        ->and(Storage::disk('r2')->exists($orphan))->toBeTrue();
});

test('prune refuses to run unless r2 is the active disk', function (): void {
    config(['filesystems.media' => 'public']);
    seedPrunableOriginal();

    expect(fn () => app(PruneVerifiedR2Originals::class)())
        ->toThrow(RuntimeException::class, 'R2 is not the active media disk.');
});

test('the prune command dry run leaves r2 unchanged', function (): void {
    [$jpg] = seedPrunableOriginal();

    expect(Artisan::call('media:prune-r2-originals'))->toBe(0)
        ->and(Artisan::output())->toContain('Dry run')
        ->and(Storage::disk('r2')->exists($jpg))->toBeTrue();
});

/** @return array{0: string, 1: string} */
function seedPrunableOriginal(): array
{
    $jpg = 'games/covers/original.jpg';
    $webp = 'games/covers/original.webp';
    $jpgBinary = 'original-bytes';
    $webpBinary = 'webp-bytes';
    Storage::disk('r2')->put($jpg, $jpgBinary);
    Storage::disk('r2')->put($webp, $webpBinary);
    Game::factory()->create(['cover_path' => $webp]);

    $operation = MediaOperation::query()->create([
        'type' => MediaOperation::TypeCleanup,
        'status' => MediaOperation::StatusFailed,
        'source_disk' => 'public',
        'target_disk' => 'public',
    ]);
    $operation->items()->create([
        'path' => $jpg,
        'path_hash' => hash('sha256', $jpg),
        'target_path' => $webp,
        'target_path_hash' => hash('sha256', $webp),
        'status' => MediaOperationItem::StatusCompleted,
        'source_checksum' => hash('sha256', $jpgBinary),
        'target_checksum' => hash('sha256', $webpBinary),
        'source_size' => strlen($jpgBinary),
    ]);

    return [$jpg, $webp];
}
