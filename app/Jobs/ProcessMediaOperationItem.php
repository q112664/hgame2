<?php

namespace App\Jobs;

use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Support\MediaImageOptimizer;
use App\Support\MediaOperationCoordinator;
use App\Support\MediaPathCollector;
use App\Support\MediaReferenceRewriter;
use App\Support\MediaStorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProcessMediaOperationItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int MaxAttempts = 3;

    public int $tries = self::MaxAttempts;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $itemId,
        public ?string $dispatchToken = null,
    ) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("media-operation-item:{$this->itemId}"))
                ->releaseAfter(10)
                ->expireAfter(180)
                ->shared(),
        ];
    }

    public function handle(
        MediaStorageManager $manager,
        MediaImageOptimizer $imageOptimizer,
        MediaPathCollector $pathCollector,
        MediaReferenceRewriter $referenceRewriter,
    ): void {
        $claimed = $this->claimItem();

        if ($claimed === null) {
            return;
        }

        [$item, $operation] = $claimed;

        try {
            if ($operation->configuration !== null) {
                $manager->configureR2($operation->configuration);
            }

            try {
                match ($operation->type) {
                    MediaOperation::TypeMigration => $this->migrate($item, $operation),
                    MediaOperation::TypeValidation => $this->validate($item, $operation),
                    MediaOperation::TypeOptimization => $this->optimize(
                        $item,
                        $operation,
                        $imageOptimizer,
                        $pathCollector,
                        $referenceRewriter,
                    ),
                    MediaOperation::TypeCleanup => $this->cleanup($item, $operation, $pathCollector),
                    default => throw new RuntimeException("Unsupported media operation [{$operation->type}]."),
                };
            } catch (Throwable $exception) {
                if ($this->isFinalAttempt()) {
                    $this->markFailed($exception);
                } else {
                    $this->releaseClaim($exception);
                }

                $manager->refreshOperationProgress($operation);

                throw $exception;
            }

            $manager->refreshOperationProgress($operation);
        } finally {
            $manager->applyRuntimeConfiguration();
        }
    }

    /** @return array{0: MediaOperationItem, 1: MediaOperation}|null */
    private function claimItem(): ?array
    {
        $item = DB::transaction(function (): ?MediaOperationItem {
            $item = MediaOperationItem::query()
                ->lockForUpdate()
                ->find($this->itemId);

            if ($item === null || in_array($item->status, [
                MediaOperationItem::StatusCompleted,
                MediaOperationItem::StatusSkipped,
                MediaOperationItem::StatusFailed,
            ], true)) {
                return null;
            }

            $now = now();
            $leaseActive = $item->status === MediaOperationItem::StatusRunning
                && $item->lease_expires_at !== null
                && $item->lease_expires_at->isAfter($now);

            if ($leaseActive) {
                return null;
            }

            $this->dispatchToken ??= (string) ($item->dispatch_token ?: Str::uuid());

            if ($item->dispatch_token !== null
                && ! hash_equals((string) $item->dispatch_token, $this->dispatchToken)) {
                return null;
            }

            $item->forceFill([
                'status' => MediaOperationItem::StatusRunning,
                'dispatch_token' => $this->dispatchToken,
                'dispatched_at' => $item->dispatched_at ?? $now,
                'lease_token' => $this->dispatchToken,
                'lease_expires_at' => $now->copy()->addSeconds(180),
                'heartbeat_at' => $now,
                'attempts' => $item->attempts + 1,
                'started_at' => $item->started_at ?? $now,
                'completed_at' => null,
                'error' => null,
            ])->save();

            return $item->refresh();
        });

        if ($item === null) {
            return null;
        }

        return [
            $item,
            MediaOperation::query()
                ->with('configuration')
                ->findOrFail($item->media_operation_id),
        ];
    }

    private function isFinalAttempt(): bool
    {
        // Direct maintenance/test calls have no queue attempt counter and
        // represent a terminal execution.
        return $this->job === null || $this->attempts() >= $this->tries;
    }

    private function releaseClaim(Throwable $exception): void
    {
        if ($this->dispatchToken === null) {
            return;
        }

        MediaOperationItem::query()
            ->whereKey($this->itemId)
            ->where('status', MediaOperationItem::StatusRunning)
            ->where('lease_token', $this->dispatchToken)
            ->update([
                'status' => MediaOperationItem::StatusPending,
                'error' => mb_substr($exception->getMessage(), 0, 4000),
                'completed_at' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'heartbeat_at' => now(),
            ]);
    }

    private function markFailed(Throwable $exception): void
    {
        $query = MediaOperationItem::query()
            ->whereKey($this->itemId)
            ->whereIn('status', [
                MediaOperationItem::StatusPending,
                MediaOperationItem::StatusRunning,
            ]);

        if ($this->dispatchToken !== null) {
            $query->where(function ($query): void {
                $query->where('lease_token', $this->dispatchToken)
                    ->orWhere('dispatch_token', $this->dispatchToken);
            });
        }

        $query->update([
            'status' => MediaOperationItem::StatusFailed,
            'error' => mb_substr($exception->getMessage(), 0, 4000),
            'completed_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
            'heartbeat_at' => now(),
        ]);
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

        $this->completeItem($item, [
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $sourceSize,
            'target_size' => $targetSize,
        ]);
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

        $remoteMetadata = $operation->target_disk === 'r2'
            ? $this->remoteMetadata($item->path)
            : [];

        $this->completeItem($item, [
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $sourceSize,
            'target_size' => $targetSize,
            'source_checksum' => $sourceChecksum,
            'target_checksum' => $targetChecksum,
            'remote_etag' => $remoteMetadata['etag'] ?? null,
            'remote_version_id' => $remoteMetadata['version_id'] ?? null,
        ]);
    }

    private function optimize(
        MediaOperationItem $item,
        MediaOperation $operation,
        MediaImageOptimizer $imageOptimizer,
        MediaPathCollector $pathCollector,
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
            $this->completeItem($item, [
                'status' => MediaOperationItem::StatusSkipped,
                'source_size' => $optimized['source_size'],
                'target_size' => $optimized['source_size'],
                'source_checksum' => $optimized['source_checksum'],
                'target_checksum' => $optimized['source_checksum'],
            ]);

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

        $rewritten = app(MediaOperationCoordinator::class)->cutover(
            fn (): int => DB::transaction(
                fn (): int => $referenceRewriter->replacePath($item->path, $targetPath, $diskName),
            ),
        );

        if ($rewritten === 0) {
            if ($pathCollector->isReferenced($targetPath)) {
                $this->completeItem($item, [
                    'status' => MediaOperationItem::StatusCompleted,
                    'source_size' => $optimized['source_size'],
                    'target_size' => $optimized['target_size'],
                    'source_checksum' => $optimized['source_checksum'],
                    'target_checksum' => $optimized['target_checksum'],
                ]);

                return;
            }

            $disk->delete($targetPath);

            if ($diskName === 'r2') {
                Storage::disk('public')->delete($targetPath);
            }

            $this->completeItem($item, [
                'status' => MediaOperationItem::StatusSkipped,
                'source_size' => $optimized['source_size'],
                'target_size' => $optimized['source_size'],
                'source_checksum' => $optimized['source_checksum'],
                'target_checksum' => $optimized['source_checksum'],
            ]);

            return;
        }

        $this->completeItem($item, [
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $optimized['source_size'],
            'target_size' => $optimized['target_size'],
            'source_checksum' => $optimized['source_checksum'],
            'target_checksum' => $optimized['target_checksum'],
        ]);
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
            $this->completeItem($item, [
                'status' => MediaOperationItem::StatusSkipped,
                'source_size' => 0,
                'target_size' => 0,
            ]);

            return;
        }

        app(MediaOperationCoordinator::class)->cutover(function () use ($item, $sourceSizes): void {
            foreach (array_keys($sourceSizes) as $diskName) {
                $disk = Storage::disk($diskName);

                if ($disk->delete($item->path) === false || $disk->exists($item->path)) {
                    throw new RuntimeException("Original media [{$item->path}] could not be deleted from [{$diskName}].");
                }
            }
        });

        $sourceSize = $sourceSizes[$sourceDisk] ?? reset($sourceSizes);

        $this->completeItem($item, [
            'status' => MediaOperationItem::StatusCompleted,
            'source_size' => $sourceSize,
            'target_size' => 0,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function completeItem(MediaOperationItem $item, array $attributes): void
    {
        if ($this->dispatchToken === null) {
            throw new RuntimeException("Media operation item [{$item->id}] has no lease token.");
        }

        $attributes['completed_at'] ??= now();
        $attributes['lease_token'] = null;
        $attributes['lease_expires_at'] = null;
        $attributes['heartbeat_at'] = now();

        $updated = MediaOperationItem::query()
            ->whereKey($item->getKey())
            ->where('status', MediaOperationItem::StatusRunning)
            ->where('lease_token', $this->dispatchToken)
            ->update($attributes);

        if ($updated !== 1) {
            throw new RuntimeException("Media operation item [{$item->id}] lease was lost.");
        }

        $item->forceFill($attributes);
    }

    /** @return array{etag?: string, version_id?: string} */
    private function remoteMetadata(string $path): array
    {
        $disk = Storage::disk('r2');

        if (! $disk instanceof AwsS3V3Adapter) {
            return [];
        }

        $config = $disk->getConfig();
        $prefix = trim((string) ($config['root'] ?? ''), '/');

        $result = $disk->getClient()->headObject([
            'Bucket' => (string) ($config['bucket'] ?? ''),
            'Key' => $prefix === '' ? ltrim($path, '/') : $prefix.'/'.ltrim($path, '/'),
        ]);

        return array_filter([
            'etag' => isset($result['ETag']) ? (string) $result['ETag'] : null,
            'version_id' => isset($result['VersionId']) ? (string) $result['VersionId'] : null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
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

        $this->markFailed($exception);

        app(MediaStorageManager::class)->refreshOperationProgress(
            MediaOperation::query()->findOrFail($item->media_operation_id),
        );
    }
}
