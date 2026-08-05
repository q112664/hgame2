<?php

namespace App\Jobs;

use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Support\MediaImageOptimizer;
use App\Support\MediaPathCollector;
use App\Support\MediaReferenceRewriter;
use App\Support\MediaStorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProcessMediaOperationItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $itemId) {}

    public function handle(
        MediaStorageManager $manager,
        MediaImageOptimizer $imageOptimizer,
        MediaPathCollector $pathCollector,
        MediaReferenceRewriter $referenceRewriter,
    ): void {
        $item = MediaOperationItem::query()->find($this->itemId);

        if ($item === null || in_array($item->status, [
            MediaOperationItem::StatusCompleted,
            MediaOperationItem::StatusSkipped,
        ], true)) {
            return;
        }

        $operation = MediaOperation::query()
            ->with('configuration')
            ->findOrFail($item->media_operation_id);

        try {
            if ($operation->configuration !== null) {
                $manager->configureR2($operation->configuration);
            }

            $item->forceFill([
                'status' => MediaOperationItem::StatusRunning,
                'attempts' => $item->attempts + 1,
                'started_at' => now(),
                'completed_at' => null,
                'error' => null,
            ])->save();

            try {
                match ($operation->type) {
                    MediaOperation::TypeMigration => $this->migrate($item, $operation),
                    MediaOperation::TypeValidation => $this->validate($item, $operation),
                    MediaOperation::TypeOptimization => $this->optimize(
                        $item,
                        $operation,
                        $imageOptimizer,
                        $referenceRewriter,
                    ),
                    MediaOperation::TypeCleanup => $this->cleanup($item, $operation, $pathCollector),
                    default => throw new RuntimeException("Unsupported media operation [{$operation->type}]."),
                };
            } catch (Throwable $exception) {
                $item->forceFill([
                    'status' => MediaOperationItem::StatusFailed,
                    'error' => mb_substr($exception->getMessage(), 0, 4000),
                    'completed_at' => now(),
                ])->save();

                $manager->refreshOperationProgress($operation);

                throw $exception;
            }

            $manager->refreshOperationProgress($operation);
        } finally {
            $manager->applyRuntimeConfiguration();
        }
    }

    private function migrate(MediaOperationItem $item, MediaOperation $operation): void
    {
        $source = Storage::disk((string) $operation->source_disk);
        $target = Storage::disk((string) $operation->target_disk);

        if (! $source->exists($item->path)) {
            throw new RuntimeException("Source media [{$item->path}] does not exist.");
        }

        $sourceSize = $source->size($item->path);
        $sourceStream = $source->readStream($item->path);

        if (! is_resource($sourceStream)) {
            throw new RuntimeException("Source media [{$item->path}] could not be opened.");
        }

        try {
            if ($target->put($item->path, $sourceStream) === false) {
                throw new RuntimeException("Target media [{$item->path}] could not be written.");
            }
        } finally {
            fclose($sourceStream);
        }

        $targetSize = $target->size($item->path);

        if ($targetSize !== $sourceSize) {
            throw new RuntimeException("Target size mismatch for [{$item->path}].");
        }

        $item->forceFill([
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $sourceSize,
            'target_size' => $targetSize,
            'completed_at' => now(),
        ])->save();
    }

    private function validate(MediaOperationItem $item, MediaOperation $operation): void
    {
        $source = Storage::disk((string) $operation->source_disk);
        $target = Storage::disk((string) $operation->target_disk);

        if (! $source->exists($item->path) || ! $target->exists($item->path)) {
            throw new RuntimeException("Media [{$item->path}] is missing on the source or target disk.");
        }

        $sourceChecksum = $this->checksum($source->readStream($item->path), 'source', $item->path);
        $targetChecksum = $this->checksum($target->readStream($item->path), 'target', $item->path);
        $sourceSize = $source->size($item->path);
        $targetSize = $target->size($item->path);

        if ($sourceSize !== $targetSize || ! hash_equals($sourceChecksum, $targetChecksum)) {
            throw new RuntimeException("Media validation failed for [{$item->path}].");
        }

        $item->forceFill([
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $sourceSize,
            'target_size' => $targetSize,
            'source_checksum' => $sourceChecksum,
            'target_checksum' => $targetChecksum,
            'completed_at' => now(),
        ])->save();
    }

    private function optimize(
        MediaOperationItem $item,
        MediaOperation $operation,
        MediaImageOptimizer $imageOptimizer,
        MediaReferenceRewriter $referenceRewriter,
    ): void {
        $diskName = (string) $operation->source_disk;
        $disk = Storage::disk($diskName);
        $targetPath = (string) $item->target_path;

        if ($targetPath === '') {
            throw new RuntimeException("No optimization target was recorded for [{$item->path}].");
        }

        if (! $disk->exists($item->path)) {
            throw new RuntimeException("Source media [{$item->path}] does not exist.");
        }

        $optimized = $imageOptimizer->optimize($diskName, $item->path);

        if ($optimized['target_size'] >= $optimized['source_size']) {
            $item->forceFill([
                'status' => MediaOperationItem::StatusSkipped,
                'source_size' => $optimized['source_size'],
                'target_size' => $optimized['source_size'],
                'source_checksum' => $optimized['source_checksum'],
                'target_checksum' => $optimized['source_checksum'],
                'completed_at' => now(),
            ])->save();

            return;
        }

        if ($disk->put($targetPath, $optimized['binary']) === false) {
            throw new RuntimeException("Optimized media [{$targetPath}] could not be written.");
        }

        $stored = $disk->get($targetPath);

        if (! is_string($stored) || ! hash_equals($optimized['target_checksum'], hash('sha256', $stored))) {
            $disk->delete($targetPath);

            throw new RuntimeException("Optimized media [{$targetPath}] failed verification.");
        }

        if ($diskName === 'r2') {
            $local = Storage::disk('public');

            if ($local->put($targetPath, $optimized['binary']) === false) {
                $disk->delete($targetPath);

                throw new RuntimeException("Local rollback media [{$targetPath}] could not be written.");
            }

            $localStored = $local->get($targetPath);

            if (! is_string($localStored) || ! hash_equals($optimized['target_checksum'], hash('sha256', $localStored))) {
                $local->delete($targetPath);
                $disk->delete($targetPath);

                throw new RuntimeException("Local rollback media [{$targetPath}] failed verification.");
            }
        }

        $rewritten = DB::transaction(
            fn (): int => $referenceRewriter->replacePath($item->path, $targetPath, $diskName),
        );

        if ($rewritten === 0) {
            $disk->delete($targetPath);

            if ($diskName === 'r2') {
                Storage::disk('public')->delete($targetPath);
            }

            $item->forceFill([
                'status' => MediaOperationItem::StatusSkipped,
                'source_size' => $optimized['source_size'],
                'target_size' => $optimized['source_size'],
                'source_checksum' => $optimized['source_checksum'],
                'target_checksum' => $optimized['source_checksum'],
                'completed_at' => now(),
            ])->save();

            return;
        }

        $item->forceFill([
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $optimized['source_size'],
            'target_size' => $optimized['target_size'],
            'source_checksum' => $optimized['source_checksum'],
            'target_checksum' => $optimized['target_checksum'],
            'completed_at' => now(),
        ])->save();
    }

    private function cleanup(
        MediaOperationItem $item,
        MediaOperation $operation,
        MediaPathCollector $pathCollector,
    ): void {
        $sourceDisk = (string) $operation->source_disk;
        $targetPath = (string) $item->target_path;

        if ($targetPath === '' || blank($item->source_checksum) || blank($item->target_checksum)) {
            throw new RuntimeException("Cleanup verification data is incomplete for [{$item->path}].");
        }

        if ($pathCollector->isReferenced($item->path)) {
            throw new RuntimeException("Original media [{$item->path}] is still referenced and cannot be deleted.");
        }

        $diskNames = $sourceDisk === 'r2' ? ['public', 'r2'] : [$sourceDisk];
        $sourceSizes = [];

        foreach ($diskNames as $diskName) {
            $disk = Storage::disk($diskName);

            if (! $disk->exists($item->path)) {
                continue;
            }

            if (! $disk->exists($targetPath)) {
                throw new RuntimeException("Optimized media [{$targetPath}] is missing from [{$diskName}].");
            }

            $targetChecksum = $this->checksum(
                $disk->readStream($targetPath),
                "optimized {$diskName}",
                $targetPath,
            );

            if (! hash_equals((string) $item->target_checksum, $targetChecksum)) {
                throw new RuntimeException("Optimized media [{$targetPath}] failed checksum verification on [{$diskName}].");
            }

            $sourceChecksum = $this->checksum(
                $disk->readStream($item->path),
                "original {$diskName}",
                $item->path,
            );

            if (! hash_equals((string) $item->source_checksum, $sourceChecksum)) {
                throw new RuntimeException("Original media [{$item->path}] changed after optimization on [{$diskName}].");
            }

            $sourceSizes[$diskName] = $disk->size($item->path);
        }

        if ($sourceSizes === []) {
            $item->forceFill([
                'status' => MediaOperationItem::StatusSkipped,
                'source_size' => 0,
                'target_size' => 0,
                'completed_at' => now(),
            ])->save();

            return;
        }

        foreach (array_keys($sourceSizes) as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->delete($item->path) === false || $disk->exists($item->path)) {
                throw new RuntimeException("Original media [{$item->path}] could not be deleted from [{$diskName}].");
            }
        }

        $sourceSize = $sourceSizes[$sourceDisk] ?? reset($sourceSizes);

        $item->forceFill([
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $sourceSize,
            'target_size' => 0,
            'completed_at' => now(),
        ])->save();
    }

    /** @param resource|false $stream */
    private function checksum(mixed $stream, string $side, string $path): string
    {
        if (! is_resource($stream)) {
            throw new RuntimeException("The {$side} stream for [{$path}] could not be opened.");
        }

        try {
            $context = hash_init('sha256');

            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    public function failed(Throwable $exception): void
    {
        $item = MediaOperationItem::query()->find($this->itemId);

        if ($item === null || in_array($item->status, [
            MediaOperationItem::StatusCompleted,
            MediaOperationItem::StatusSkipped,
        ], true)) {
            return;
        }

        $item->forceFill([
            'status' => MediaOperationItem::StatusFailed,
            'error' => mb_substr($exception->getMessage(), 0, 4000),
            'completed_at' => now(),
        ])->save();

        app(MediaStorageManager::class)->refreshOperationProgress(
            MediaOperation::query()->findOrFail($item->media_operation_id),
        );
    }
}
