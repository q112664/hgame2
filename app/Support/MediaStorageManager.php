<?php

namespace App\Support;

use App\Jobs\ProcessMediaOperationItem;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Models\MediaStorageConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaStorageManager
{
    public function __construct(
        private readonly MediaPathCollector $pathCollector,
        private readonly MediaImageOptimizer $imageOptimizer,
        private readonly MediaReferenceRewriter $referenceRewriter,
        private readonly MediaOperationCoordinator $coordinator,
    ) {}

    /**
     * @param array{
     *     account_id: string,
     *     access_key_id: string,
     *     secret_access_key?: string|null,
     *     bucket: string,
     *     public_url: string,
     *     region?: string|null
     * } $data
     */
    public function saveConfiguration(array $data, ?MediaStorageConfiguration $existing = null): MediaStorageConfiguration
    {
        $accountId = trim($data['account_id']);
        $accessKeyId = trim($data['access_key_id']);
        $secretAccessKey = trim((string) ($data['secret_access_key'] ?? ''));
        $bucket = trim($data['bucket']);
        $publicUrl = rtrim(trim($data['public_url']), '/');
        $region = trim((string) ($data['region'] ?? 'auto')) ?: 'auto';

        if ($secretAccessKey === '' && $existing !== null) {
            $secretAccessKey = (string) $existing->secret_access_key;
        }

        $this->validateConfigurationValues(
            $accountId,
            $accessKeyId,
            $secretAccessKey,
            $bucket,
            $publicUrl,
        );

        $fingerprint = $this->configurationFingerprint([
            'account_id' => $accountId,
            'access_key_id' => $accessKeyId,
            'secret_access_key' => $secretAccessKey,
            'bucket' => $bucket,
            'public_url' => $publicUrl,
            'region' => $region,
        ]);

        if ($existing !== null && hash_equals((string) $existing->configuration_fingerprint, $fingerprint)) {
            return $existing;
        }

        return MediaStorageConfiguration::query()->create([
            'provider' => 'cloudflare_r2',
            'account_id' => $accountId,
            'access_key_id' => $accessKeyId,
            'secret_access_key' => $secretAccessKey,
            'bucket' => $bucket,
            'public_url' => $publicUrl,
            'region' => $region,
            'configuration_fingerprint' => $fingerprint,
            'is_active' => false,
        ]);
    }

    public function testConnection(MediaStorageConfiguration $configuration): void
    {
        $this->configureR2($configuration);
        $path = 'media-healthchecks/'.Str::uuid()->toString().'.txt';
        $token = Str::random(48);

        try {
            $disk = Storage::disk('r2');

            if ($disk->put($path, $token) === false) {
                throw new RuntimeException('The R2 test object could not be uploaded.');
            }

            if ($disk->get($path) !== $token) {
                throw new RuntimeException('The R2 test object could not be read back correctly.');
            }

            $publicResponse = Http::timeout(10)
                ->retry(3, 250)
                ->get(rtrim((string) $configuration->public_url, '/').'/'.$path, [
                    'media_healthcheck' => $token,
                ]);

            if (! $publicResponse->successful() || $publicResponse->body() !== $token) {
                throw new RuntimeException('The R2 public URL could not read the test object correctly.');
            }

            if ($disk->delete($path) === false || $disk->exists($path)) {
                throw new RuntimeException('The R2 test object could not be deleted.');
            }

            $configuration->forceFill([
                'tested_fingerprint' => $configuration->configuration_fingerprint,
                'connection_tested_at' => now(),
                'connection_test_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            rescue(fn () => Storage::disk('r2')->delete($path), report: false);

            $configuration->forceFill([
                'tested_fingerprint' => null,
                'connection_tested_at' => now(),
                'connection_test_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        } finally {
            $this->applyRuntimeConfiguration();
        }
    }

    public function startMigration(MediaStorageConfiguration $configuration, ?User $user = null): MediaOperation
    {
        $this->assertTested($configuration);
        $this->assertLocalMediaComplete();

        return $this->createOperation(
            type: MediaOperation::TypeMigration,
            configuration: $configuration,
            paths: $this->pathCollector->all('public'),
            sourceDisk: 'public',
            targetDisk: 'r2',
            user: $user,
            metadata: [
                'source_manifest' => $this->sourceManifest('public'),
            ],
        );
    }

    public function startValidation(MediaStorageConfiguration $configuration, ?User $user = null): MediaOperation
    {
        $this->assertTested($configuration);
        $migration = $this->latestSuccessfulOperation(MediaOperation::TypeMigration, $configuration);

        if ($migration === null) {
            throw new RuntimeException('A successful migration for this R2 configuration is required first.');
        }

        $this->assertLocalMediaComplete();

        $paths = $this->pathCollector->all('public');
        $migrationPaths = array_values(array_map(
            static fn (mixed $path): string => (string) $path,
            $migration->items()->orderBy('path')->pluck('path')->all(),
        ));

        if (! $this->samePathSet($paths, $migrationPaths)) {
            throw new RuntimeException('Local media changed after migration. Run the migration again before validation.');
        }

        return $this->createOperation(
            type: MediaOperation::TypeValidation,
            configuration: $configuration,
            paths: $paths,
            sourceDisk: 'public',
            targetDisk: 'r2',
            user: $user,
            metadata: [
                'migration_id' => $migration->getKey(),
                'source_manifest' => $this->sourceManifest('public'),
            ],
        );
    }

    public function startOptimization(?User $user = null): MediaOperation
    {
        $disk = Media::diskName();
        $configuration = $disk === 'r2' ? MediaStorageConfiguration::active() : null;

        return $this->createOperation(
            type: MediaOperation::TypeOptimization,
            configuration: $configuration,
            paths: $this->pathCollector->optimizable($disk),
            sourceDisk: $disk,
            targetDisk: $disk,
            user: $user,
            targetPaths: true,
            metadata: [
                'quality' => MediaImageOptimizer::Quality,
                'cover_max_dimension' => MediaImageOptimizer::CoverMaxDimension,
                'screenshot_max_dimension' => MediaImageOptimizer::ScreenshotMaxDimension,
                'source_files_retained' => true,
            ],
        );
    }

    /** @return array{operation_id: int|null, files: int, bytes: int} */
    public function cleanupPreview(): array
    {
        $optimization = $this->latestCleanupCandidate();

        if ($optimization === null) {
            return [
                'operation_id' => null,
                'files' => 0,
                'bytes' => 0,
            ];
        }

        $items = $this->cleanupCandidateItems($optimization);

        return [
            'operation_id' => (int) $optimization->getKey(),
            'files' => $items->count(),
            'bytes' => (int) $items->sum('source_size'),
        ];
    }

    public function startCleanup(?User $user = null): MediaOperation
    {
        $optimization = $this->latestCleanupCandidate();

        if ($optimization === null) {
            throw new RuntimeException('No completed image optimization has original files ready for cleanup.');
        }

        $items = $this->cleanupCandidateItems($optimization);

        if ($items->isEmpty()) {
            throw new RuntimeException('No verified, unreferenced original images are available to delete.');
        }

        return $this->createOperation(
            type: MediaOperation::TypeCleanup,
            configuration: $optimization->configuration,
            paths: array_values($items->map(fn (MediaOperationItem $item): string => $item->path)->all()),
            sourceDisk: (string) $optimization->source_disk,
            targetDisk: (string) $optimization->target_disk,
            user: $user,
            metadata: [
                'optimization_id' => (int) $optimization->getKey(),
                'estimated_reclaimable_bytes' => (int) $items->sum('source_size'),
                'permanent_deletion' => true,
            ],
            itemDetails: $items->mapWithKeys(fn (MediaOperationItem $item): array => [
                $item->path => [
                    'target_path' => $item->target_path,
                    'source_checksum' => $item->source_checksum,
                    'target_checksum' => $item->target_checksum,
                ],
            ])->all(),
            configurationFingerprint: $optimization->configuration_fingerprint,
        );
    }

    public function retryFailed(MediaOperation $operation): MediaOperation
    {
        return $this->coordinator->operation(function () use ($operation): MediaOperation {
            $operation = DB::transaction(function () use ($operation): MediaOperation {
                $locked = MediaOperation::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $operation->getKey());

                if (! in_array($locked->status, [MediaOperation::StatusFailed, MediaOperation::StatusCompleted], true)) {
                    throw new RuntimeException('Only finished operations can retry failed items.');
                }

                $itemIds = $locked->items()
                    ->where('status', MediaOperationItem::StatusFailed)
                    ->pluck('id')
                    ->all();

                if ($itemIds === []) {
                    throw new RuntimeException('This operation has no failed items to retry.');
                }

                $this->assertNoOperationRunning();

                $locked->items()->whereKey($itemIds)->update([
                    'status' => MediaOperationItem::StatusPending,
                    'attempts' => 0,
                    'error' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'dispatch_token' => null,
                    'dispatched_at' => null,
                    'lease_token' => null,
                    'lease_expires_at' => null,
                    'heartbeat_at' => null,
                ]);

                $locked->forceFill([
                    'status' => MediaOperation::StatusRunning,
                    'running_slot' => 1,
                    'error' => null,
                    'completed_at' => null,
                ])->save();

                return $locked;
            });

            $this->refreshOperationProgress($operation);
            $this->dispatchPending($operation);

            return $operation->refresh();
        });
    }

    /**
     * Dispatch pending items through a durable item-level outbox.
     *
     * The database row is committed before the queue push. If the queue
     * backend is unavailable, the row remains pending and the recovery
     * command can safely retry it later.
     */
    public function dispatchPending(MediaOperation $operation): int
    {
        $dispatched = 0;
        $staleBefore = now()->subSeconds(60);

        $operation->items()
            ->where('status', MediaOperationItem::StatusPending)
            ->where(function (Builder $query) use ($staleBefore): void {
                $query->whereNull('dispatch_token')
                    ->orWhereNull('dispatched_at')
                    ->orWhere('dispatched_at', '<=', $staleBefore);
            })
            ->orderBy('id')
            ->each(function (MediaOperationItem $candidate) use (&$dispatched): void {
                $token = (string) Str::uuid();
                $reserved = DB::transaction(function () use ($candidate, $token): bool {
                    $item = MediaOperationItem::query()
                        ->lockForUpdate()
                        ->find((int) $candidate->getKey());

                    if ($item === null || $item->status !== MediaOperationItem::StatusPending) {
                        return false;
                    }

                    if ($item->dispatch_token !== null
                        && $item->dispatched_at !== null
                        && $item->dispatched_at->isAfter(now()->subSeconds(60))) {
                        return false;
                    }

                    $item->forceFill([
                        'dispatch_token' => $token,
                        'dispatched_at' => now(),
                        'error' => null,
                    ])->save();

                    return true;
                });

                if (! $reserved) {
                    return;
                }

                try {
                    ProcessMediaOperationItem::dispatch(
                        (int) $candidate->getKey(),
                        $token,
                    );
                    $dispatched++;
                } catch (Throwable $exception) {
                    $operation = $candidate->operation()->first();

                    if ($operation !== null) {
                        $operation->forceFill([
                            'error' => 'Queue dispatch failed: '.mb_substr($exception->getMessage(), 0, 3900),
                        ])->save();
                    }
                }
            });

        return $dispatched;
    }

    public function activate(MediaStorageConfiguration $configuration): void
    {
        $this->coordinator->operation(function () use ($configuration): void {
            $this->coordinator->cutover(function () use ($configuration): void {
                $this->activateUnderLock($configuration);
            });
        });
    }

    private function activateUnderLock(MediaStorageConfiguration $configuration): void
    {
        $this->assertNoOperationRunning();
        $this->assertTested($configuration);
        $validation = $this->latestSuccessfulOperation(MediaOperation::TypeValidation, $configuration);

        if ($validation === null) {
            throw new RuntimeException('A successful validation for this R2 configuration is required first.');
        }

        $previousActive = MediaStorageConfiguration::active();

        try {
            // The active runtime may still point at a different R2 configuration.
            // Switch the filesystem client to the candidate before preflight checks.
            $this->configureR2($configuration);
            $this->assertValidationIsCurrent($validation);
            $this->assertRemoteMediaIsCurrent($validation, (string) $configuration->public_url);

            DB::transaction(function () use ($configuration, $previousActive): void {
                MediaStorageConfiguration::query()
                    ->where(function (Builder $query): void {
                        $query->where('is_active', true)->orWhere('active_slot', 1);
                    })
                    ->update([
                        'is_active' => false,
                        'active_slot' => null,
                        'activated_at' => null,
                    ]);

                $configuration->forceFill([
                    'is_active' => true,
                    'active_slot' => 1,
                    'activated_at' => now(),
                ])->save();

                $this->referenceRewriter->activateR2(
                    (string) $configuration->public_url,
                    $previousActive?->public_url,
                );
            });
        } finally {
            // A failed preflight or rewrite must leave the process using the
            // database-backed active configuration, not the candidate client.
            $this->applyRuntimeConfiguration();
        }
    }

    public function rollbackToLocal(MediaStorageConfiguration $configuration): void
    {
        $this->coordinator->operation(function () use ($configuration): void {
            $this->coordinator->cutover(function () use ($configuration): void {
                $this->rollbackToLocalUnderLock($configuration);
            });
        });
    }

    private function rollbackToLocalUnderLock(MediaStorageConfiguration $configuration): void
    {
        $this->assertNoOperationRunning();
        if (! $configuration->is_active) {
            throw new RuntimeException('Only the active R2 configuration can be rolled back.');
        }

        $validation = $this->latestSuccessfulOperation(MediaOperation::TypeValidation, $configuration);

        if ($validation === null) {
            throw new RuntimeException('Rollback is blocked because no successful media validation is available.');
        }

        $this->assertLocalRollbackMediaIsCurrent($validation);

        DB::transaction(function () use ($configuration): void {
            $this->referenceRewriter->rollbackToLocal((string) $configuration->public_url);
            $configuration->forceFill([
                'is_active' => false,
                'active_slot' => null,
                'activated_at' => null,
            ])->save();
        });

        $this->applyRuntimeConfiguration();
    }

    public function configureR2(MediaStorageConfiguration $configuration): void
    {
        $currentFingerprint = config('filesystems.disks.r2.configuration_fingerprint');

        config([
            'filesystems.disks.r2' => [
                'driver' => 'r2',
                'key' => (string) $configuration->access_key_id,
                'secret' => (string) $configuration->secret_access_key,
                'region' => (string) ($configuration->region ?: 'auto'),
                'bucket' => (string) $configuration->bucket,
                'url' => rtrim((string) $configuration->public_url, '/'),
                'endpoint' => $configuration->endpoint(),
                'use_path_style_endpoint' => false,
                'throw' => true,
                'report' => true,
                'configuration_fingerprint' => (string) $configuration->configuration_fingerprint,
            ],
        ]);

        if (
            ! app()->environment('testing')
            && $currentFingerprint !== $configuration->configuration_fingerprint
        ) {
            Storage::forgetDisk('r2');
        }
    }

    public function applyRuntimeConfiguration(): void
    {
        $active = MediaStorageConfiguration::active();

        if ($active === null) {
            config([
                'filesystems.media' => 'public',
                'filesystems.disks.r2' => $this->emptyR2Configuration(),
            ]);

            if (! app()->environment('testing')) {
                Storage::forgetDisk('r2');
            }

            return;
        }

        $this->configureR2($active);
        config(['filesystems.media' => 'r2']);
    }

    /** @return array<string, mixed> */
    private function emptyR2Configuration(): array
    {
        return [
            'driver' => 'r2',
            'key' => null,
            'secret' => null,
            'region' => 'auto',
            'bucket' => null,
            'url' => null,
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => true,
            'configuration_fingerprint' => null,
        ];
    }

    public function refreshOperationProgress(MediaOperation $operation): void
    {
        DB::transaction(function () use ($operation): void {
            $locked = MediaOperation::query()
                ->whereKey($operation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $counts = $locked->items()
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
            $processed = (int) collect([
                MediaOperationItem::StatusCompleted,
                MediaOperationItem::StatusSkipped,
                MediaOperationItem::StatusFailed,
            ])->sum(fn (string $status): int => (int) ($counts[$status] ?? 0));
            $failed = (int) ($counts[MediaOperationItem::StatusFailed] ?? 0);
            $sourceBytes = (int) $locked->items()->sum('source_size');
            $targetBytes = (int) $locked->items()->sum('target_size');
            $finished = $processed >= $locked->total_items;

            $locked->forceFill([
                'processed_items' => $processed,
                'succeeded_items' => (int) ($counts[MediaOperationItem::StatusCompleted] ?? 0),
                'skipped_items' => (int) ($counts[MediaOperationItem::StatusSkipped] ?? 0),
                'failed_items' => $failed,
                'total_source_bytes' => $sourceBytes,
                'total_target_bytes' => $targetBytes,
                'status' => $finished
                    ? ($failed > 0 ? MediaOperation::StatusFailed : MediaOperation::StatusCompleted)
                    : MediaOperation::StatusRunning,
                'running_slot' => $finished ? null : 1,
                'completed_at' => $finished ? now() : null,
            ])->save();
        });
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, mixed>  $metadata
     * @param  array<string, array{
     *     target_path?: string|null,
     *     source_size?: int|null,
     *     target_size?: int|null,
     *     source_checksum?: string|null,
     *     target_checksum?: string|null
     * }>  $itemDetails
     */
    private function createOperation(
        string $type,
        ?MediaStorageConfiguration $configuration,
        array $paths,
        string $sourceDisk,
        string $targetDisk,
        ?User $user,
        array $metadata,
        bool $targetPaths = false,
        array $itemDetails = [],
        ?string $configurationFingerprint = null,
    ): MediaOperation {
        $operation = $this->coordinator->operation(function () use (
            $type,
            $configuration,
            $paths,
            $sourceDisk,
            $targetDisk,
            $user,
            $metadata,
            $targetPaths,
            $itemDetails,
            $configurationFingerprint,
        ): MediaOperation {
            $this->assertNoOperationRunning();

            return DB::transaction(function () use (
                $type,
                $configuration,
                $paths,
                $sourceDisk,
                $targetDisk,
                $user,
                $metadata,
                $targetPaths,
                $itemDetails,
                $configurationFingerprint,
            ): MediaOperation {
                $operation = MediaOperation::query()->create([
                    'media_storage_configuration_id' => $configuration?->getKey(),
                    'user_id' => $user?->getKey(),
                    'type' => $type,
                    'status' => $paths === [] ? MediaOperation::StatusCompleted : MediaOperation::StatusRunning,
                    'running_slot' => $paths === [] ? null : 1,
                    'source_disk' => $sourceDisk,
                    'target_disk' => $targetDisk,
                    'configuration_fingerprint' => $configurationFingerprint
                        ?? $configuration?->configuration_fingerprint,
                    'total_items' => count($paths),
                    'metadata' => $metadata,
                    'started_at' => now(),
                    'completed_at' => $paths === [] ? now() : null,
                ]);
                $reservedTargetPaths = [];

                foreach ($paths as $path) {
                    $details = $itemDetails[$path] ?? [];
                    $targetPath = $details['target_path'] ?? null;

                    if ($targetPaths && $targetPath === null) {
                        $targetPath = $this->imageOptimizer->targetPath($path);

                        if ($targetPath !== $path) {
                            $collisionIndex = 0;

                            while (isset($reservedTargetPaths[$targetPath])
                                || Storage::disk($targetDisk)->exists($targetPath)) {
                                $collisionIndex++;
                                $collisionTarget = $this->imageOptimizer->targetPath($path, true);

                                if ($collisionIndex > 1) {
                                    $collisionTarget = pathinfo($collisionTarget, PATHINFO_DIRNAME).'/'.pathinfo($collisionTarget, PATHINFO_FILENAME)
                                        .'-'.$collisionIndex.'.webp';
                                }

                                $targetPath = $collisionTarget;
                            }

                            $reservedTargetPaths[$targetPath] = true;
                        }
                    }

                    $operation->items()->create([
                        'path' => $path,
                        'path_hash' => hash('sha256', $path),
                        'target_path' => $targetPath,
                        'target_path_hash' => $targetPath === null ? null : hash('sha256', $targetPath),
                        'status' => MediaOperationItem::StatusPending,
                        'source_size' => $details['source_size'] ?? null,
                        'target_size' => $details['target_size'] ?? null,
                        'source_checksum' => $details['source_checksum'] ?? null,
                        'target_checksum' => $details['target_checksum'] ?? null,
                    ]);
                }

                return $operation;
            });
        });

        $this->dispatchPending($operation);

        return $operation;
    }

    private function latestCleanupCandidate(): ?MediaOperation
    {
        return MediaOperation::query()
            ->where('type', MediaOperation::TypeOptimization)
            ->where('status', MediaOperation::StatusCompleted)
            ->latest('id')
            ->get()
            ->first(fn (MediaOperation $operation): bool => $this->cleanupCandidateItems($operation)->isNotEmpty());
    }

    /** @return EloquentCollection<int, MediaOperationItem> */
    private function cleanupCandidateItems(MediaOperation $optimization): EloquentCollection
    {
        $cleanupOperationIds = MediaOperation::query()
            ->where('type', MediaOperation::TypeCleanup)
            ->where('metadata->optimization_id', (int) $optimization->getKey())
            ->pluck('id');
        $cleanedPaths = MediaOperationItem::query()
            ->whereIn('media_operation_id', $cleanupOperationIds)
            ->pluck('path')
            ->all();
        $referencedPaths = array_flip($this->pathCollector->references());

        return $optimization->items()
            ->where('status', MediaOperationItem::StatusCompleted)
            ->whereNotNull('target_path')
            ->whereNotNull('source_checksum')
            ->whereNotNull('target_checksum')
            ->when($cleanedPaths !== [], fn (Builder $query): Builder => $query->whereNotIn('path', $cleanedPaths))
            ->orderBy('id')
            ->get()
            ->filter(fn (MediaOperationItem $item): bool => ! isset($referencedPaths[$item->path]))
            ->values();
    }

    private function assertNoOperationRunning(): void
    {
        if (MediaOperation::query()
            ->where(function (Builder $query): void {
                $query->where('running_slot', 1)
                    ->orWhereIn('status', [
                        MediaOperation::StatusPending,
                        MediaOperation::StatusRunning,
                    ]);
            })
            ->exists()) {
            throw new RuntimeException('Another media operation is already running.');
        }
    }

    private function assertLocalMediaComplete(): void
    {
        $missing = array_slice($this->pathCollector->missing('public'), 0, 10);

        if ($missing !== []) {
            throw new RuntimeException(
                'Required local media is missing, including rollback copies or cover thumbnails: '
                .implode(', ', $missing),
            );
        }
    }

    private function assertTested(MediaStorageConfiguration $configuration): void
    {
        if (! $configuration->wasSuccessfullyTested()) {
            throw new RuntimeException('Test this exact R2 configuration successfully before continuing.');
        }
    }

    private function latestSuccessfulOperation(
        string $type,
        MediaStorageConfiguration $configuration,
    ): ?MediaOperation {
        return MediaOperation::query()
            ->where('type', $type)
            ->where('status', MediaOperation::StatusCompleted)
            ->where('configuration_fingerprint', $configuration->configuration_fingerprint)
            ->latest('id')
            ->first();
    }

    private function assertValidationIsCurrent(MediaOperation $validation): void
    {
        $paths = $this->pathCollector->all('public');
        $validatedItems = $validation->items()
            ->orderBy('path')
            ->get(['path', 'source_size', 'source_checksum']);

        $validatedPaths = array_values(array_map(
            static fn (mixed $path): string => (string) $path,
            $validatedItems->pluck('path')->all(),
        ));

        if (! $this->samePathSet($paths, $validatedPaths)) {
            throw new RuntimeException('Local media changed after validation. Run migration and validation again.');
        }

        foreach ($validatedItems as $item) {
            if (! Storage::disk('public')->exists($item->path)) {
                throw new RuntimeException("Local media [{$item->path}] is missing after validation.");
            }

            if (Storage::disk('public')->size($item->path) !== (int) $item->source_size) {
                throw new RuntimeException("Local media [{$item->path}] changed after validation.");
            }

            $checksum = $this->streamChecksum(
                Storage::disk('public')->readStream($item->path),
                $item->path,
            );

            if (! hash_equals((string) $item->source_checksum, $checksum)) {
                throw new RuntimeException("Local media [{$item->path}] changed after validation.");
            }
        }
    }

    private function assertLocalRollbackMediaIsCurrent(MediaOperation $validation): void
    {
        $validated = $validation->items()
            ->whereNotNull('source_checksum')
            ->get(['path', 'source_size', 'source_checksum'])
            ->keyBy('path');
        $requiredPaths = $this->pathCollector->required();
        $remote = Storage::disk('r2');

        foreach ($requiredPaths as $path) {
            $local = Storage::disk('public');

            if (! $local->exists($path)) {
                throw new RuntimeException("Local rollback media [{$path}] is missing.");
            }

            $item = $validated->get($path);

            if ($item !== null) {
                if ($local->size($path) !== (int) $item->source_size) {
                    throw new RuntimeException("Local rollback media [{$path}] changed after validation.");
                }

                $checksum = $this->streamChecksum($local->readStream($path), $path);

                if (! hash_equals((string) $item->source_checksum, $checksum)) {
                    throw new RuntimeException("Local rollback media [{$path}] changed after validation.");
                }

                continue;
            }

            // Media uploaded after activation was not part of the immutable
            // migration validation. Verify its local rollback copy against R2
            // before switching URLs back to local.
            if (! $remote->exists($path)) {
                throw new RuntimeException("R2 media [{$path}] is missing for rollback verification.");
            }

            if ($remote->size($path) !== $local->size($path)) {
                throw new RuntimeException("Local rollback media [{$path}] does not match R2.");
            }

            $localChecksum = $this->streamChecksum($local->readStream($path), $path);
            $remoteChecksum = $this->streamChecksum($remote->readStream($path), $path);

            if (! hash_equals($localChecksum, $remoteChecksum)) {
                throw new RuntimeException("Local rollback media [{$path}] does not match R2.");
            }
        }
    }

    private function assertRemoteMediaIsCurrent(MediaOperation $validation, string $publicUrl): void
    {
        $remote = Storage::disk('r2');
        $items = $validation->items()
            ->orderBy('path')
            ->get(['path', 'target_size', 'target_checksum', 'remote_etag', 'remote_version_id']);
        $remoteManifest = $this->remoteManifest();

        if ($remoteManifest !== null) {
            foreach ($items as $item) {
                $path = (string) $item->path;
                $entry = $remoteManifest[$path] ?? null;

                if ($entry === null) {
                    throw new RuntimeException("R2 media [{$path}] is missing before activation.");
                }

                if ($item->target_size !== null && $entry['size'] !== (int) $item->target_size) {
                    throw new RuntimeException("R2 media [{$path}] changed before activation.");
                }

                if ($item->remote_etag !== null && $entry['etag'] !== null
                    && ! hash_equals($this->normalizeRemoteTag((string) $item->remote_etag), $this->normalizeRemoteTag($entry['etag']))) {
                    throw new RuntimeException("R2 media [{$path}] changed before activation.");
                }

                if ($item->remote_version_id !== null && $entry['version_id'] !== null
                    && ! hash_equals((string) $item->remote_version_id, $entry['version_id'])) {
                    throw new RuntimeException("R2 media [{$path}] changed before activation.");
                }
            }
        } else {
            $items->each(function (MediaOperationItem $item) use ($remote): void {
                $path = (string) $item->path;

                if (! $remote->exists($path)) {
                    throw new RuntimeException("R2 media [{$path}] is missing before activation.");
                }

                if ($item->target_size !== null && $remote->size($path) !== (int) $item->target_size) {
                    throw new RuntimeException("R2 media [{$path}] changed before activation.");
                }

                if ($item->target_checksum !== null) {
                    $checksum = $this->streamChecksum($remote->readStream($path), $path);

                    if (! hash_equals((string) $item->target_checksum, $checksum)) {
                        throw new RuntimeException("R2 media [{$path}] changed before activation.");
                    }
                }
            });
        }

        $probePath = 'media-healthchecks/activation-'.Str::uuid()->toString().'.txt';
        $probeToken = Str::random(48);

        if ($remote->put($probePath, $probeToken) === false) {
            throw new RuntimeException('The R2 activation probe could not be written.');
        }

        try {
            $response = Http::timeout(10)
                ->retry(3, 250)
                ->get($this->publicMediaUrl($publicUrl, $probePath), [
                    'media_healthcheck' => $probeToken,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The R2 public URL could not be reached before activation.', 0, $exception);
        } finally {
            rescue(fn () => $remote->delete($probePath), report: false);
        }

        if (! $response->successful() || $response->body() !== $probeToken) {
            throw new RuntimeException('The R2 public URL failed the activation body probe.');
        }
    }

    /** @return array<string, array{size: int, etag: string|null, version_id: string|null}>|null */
    private function remoteManifest(): ?array
    {
        $remote = Storage::disk('r2');

        if (! $remote instanceof AwsS3V3Adapter) {
            return null;
        }

        $config = $remote->getConfig();
        $client = $remote->getClient();
        $root = trim((string) ($config['root'] ?? ''), '/');
        $manifest = [];

        foreach (['avatars/', 'docs/', 'games/', 'site/'] as $managedPrefix) {
            $continuationToken = null;

            do {
                $arguments = [
                    'Bucket' => (string) ($config['bucket'] ?? ''),
                    'Prefix' => $root === '' ? $managedPrefix : $root.'/'.$managedPrefix,
                    'MaxKeys' => 1000,
                ];

                if ($continuationToken !== null) {
                    $arguments['ContinuationToken'] = $continuationToken;
                }

                $result = $client->listObjectsV2($arguments);

                foreach ((array) ($result['Contents'] ?? []) as $object) {
                    $key = (string) ($object['Key'] ?? '');
                    $relative = $root === '' ? $key : ltrim(substr($key, strlen($root)), '/');

                    if ($relative === '') {
                        continue;
                    }

                    $manifest[$relative] = [
                        'size' => (int) ($object['Size'] ?? 0),
                        'etag' => isset($object['ETag']) ? (string) $object['ETag'] : null,
                        'version_id' => isset($object['VersionId']) ? (string) $object['VersionId'] : null,
                    ];
                }

                $continuationToken = ($result['IsTruncated'] ?? false)
                    ? (string) ($result['NextContinuationToken'] ?? '')
                    : null;
            } while ($continuationToken !== null && $continuationToken !== '');
        }

        return $manifest;
    }

    private function normalizeRemoteTag(string $tag): string
    {
        return trim($tag, " \t\n\r\0\x0B\"");
    }

    private function publicMediaUrl(string $publicUrl, string $path): string
    {
        $encodedPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', ltrim($path, '/')),
        ));

        return rtrim($publicUrl, '/').'/'.$encodedPath;
    }

    /** @param resource|false $stream */
    private function streamChecksum(mixed $stream, string $path): string
    {
        if (! is_resource($stream)) {
            throw new RuntimeException("Local media [{$path}] could not be read for activation.");
        }

        try {
            $context = hash_init('sha256');

            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function samePathSet(array $left, array $right): bool
    {
        return count($left) === count($right)
            && array_diff($left, $right) === []
            && array_diff($right, $left) === [];
    }

    /** @return array{count: int, fingerprint: string} */
    private function sourceManifest(string $disk): array
    {
        $entries = [];

        foreach ($this->pathCollector->all($disk) as $path) {
            $size = Storage::disk($disk)->exists($path)
                ? Storage::disk($disk)->size($path)
                : 'missing';
            $entries[] = $path.':'.$size;
        }

        return [
            'count' => count($entries),
            'fingerprint' => hash('sha256', implode("\n", $entries)),
        ];
    }

    /**
     * @param array{
     *     account_id: string,
     *     access_key_id: string,
     *     secret_access_key: string,
     *     bucket: string,
     *     public_url: string,
     *     region: string
     * } $values
     */
    private function configurationFingerprint(array $values): string
    {
        ksort($values);

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    private function validateConfigurationValues(
        string $accountId,
        string $accessKeyId,
        string $secretAccessKey,
        string $bucket,
        string $publicUrl,
    ): void {
        if ($accountId === '' || $accessKeyId === '' || $secretAccessKey === '' || $bucket === '') {
            throw new InvalidArgumentException('Account ID, access key, secret key, and bucket are required.');
        }

        if (filter_var($publicUrl, FILTER_VALIDATE_URL) === false || ! str_starts_with($publicUrl, 'https://')) {
            throw new InvalidArgumentException('The public media URL must be a valid HTTPS URL.');
        }

        $host = strtolower((string) parse_url($publicUrl, PHP_URL_HOST));

        if ($host === '' || str_ends_with($host, '.r2.dev')) {
            throw new InvalidArgumentException('Use a Cloudflare R2 custom domain, not an r2.dev URL.');
        }

        if (trim((string) parse_url($publicUrl, PHP_URL_PATH), '/') !== ''
            || parse_url($publicUrl, PHP_URL_QUERY) !== null
            || parse_url($publicUrl, PHP_URL_FRAGMENT) !== null) {
            throw new InvalidArgumentException('The public media URL must point to the domain root without a path prefix.');
        }
    }
}
