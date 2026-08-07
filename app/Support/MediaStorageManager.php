<?php

namespace App\Support;

use App\Jobs\ProcessMediaOperationItem;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Models\MediaStorageConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        if (! in_array($operation->status, [MediaOperation::StatusFailed, MediaOperation::StatusCompleted], true)) {
            throw new RuntimeException('Only finished operations can retry failed items.');
        }

        $itemIds = $operation->items()
            ->where('status', MediaOperationItem::StatusFailed)
            ->pluck('id')
            ->all();

        if ($itemIds === []) {
            throw new RuntimeException('This operation has no failed items to retry.');
        }

        $this->assertNoOperationRunning();

        $operation->items()->whereKey($itemIds)->update([
            'status' => MediaOperationItem::StatusPending,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        $operation->forceFill([
            'status' => MediaOperation::StatusRunning,
            'error' => null,
            'completed_at' => null,
        ])->save();
        $this->refreshOperationProgress($operation);

        foreach ($itemIds as $itemId) {
            ProcessMediaOperationItem::dispatch($itemId);
        }

        return $operation->refresh();
    }

    public function activate(MediaStorageConfiguration $configuration): void
    {
        $this->assertTested($configuration);
        $validation = $this->latestSuccessfulOperation(MediaOperation::TypeValidation, $configuration);

        if ($validation === null) {
            throw new RuntimeException('A successful validation for this R2 configuration is required first.');
        }

        $this->assertValidationIsCurrent($validation);
        $previousActive = MediaStorageConfiguration::active();

        DB::transaction(function () use ($configuration, $previousActive): void {
            MediaStorageConfiguration::query()->where('is_active', true)->update([
                'is_active' => false,
                'activated_at' => null,
            ]);

            $configuration->forceFill([
                'is_active' => true,
                'activated_at' => now(),
            ])->save();

            $this->referenceRewriter->activateR2(
                (string) $configuration->public_url,
                $previousActive?->public_url,
            );
        });

        $this->applyRuntimeConfiguration();
    }

    public function rollbackToLocal(MediaStorageConfiguration $configuration): void
    {
        if (! $configuration->is_active) {
            throw new RuntimeException('Only the active R2 configuration can be rolled back.');
        }

        $missing = array_slice($this->pathCollector->missing('public'), 0, 10);

        if ($missing !== []) {
            throw new RuntimeException('Rollback is blocked because some referenced media is missing locally: '.implode(', ', $missing));
        }

        DB::transaction(function () use ($configuration): void {
            $this->referenceRewriter->rollbackToLocal((string) $configuration->public_url);
            $configuration->forceFill([
                'is_active' => false,
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
        $this->assertNoOperationRunning();

        $operation = DB::transaction(function () use (
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
                'source_disk' => $sourceDisk,
                'target_disk' => $targetDisk,
                'configuration_fingerprint' => $configurationFingerprint
                    ?? $configuration?->configuration_fingerprint,
                'total_items' => count($paths),
                'metadata' => $metadata,
                'started_at' => now(),
                'completed_at' => $paths === [] ? now() : null,
            ]);

            foreach ($paths as $path) {
                $details = $itemDetails[$path] ?? [];
                $targetPath = $details['target_path'] ?? null;

                if ($targetPaths && $targetPath === null) {
                    $targetPath = $this->imageOptimizer->targetPath($path);

                    if ($targetPath !== $path && Storage::disk($targetDisk)->exists($targetPath)) {
                        $targetPath = $this->imageOptimizer->targetPath($path, true);
                    }
                }

                $operation->items()->create([
                    'path' => $path,
                    'path_hash' => hash('sha256', $path),
                    'target_path' => $targetPath,
                    'status' => MediaOperationItem::StatusPending,
                    'source_size' => $details['source_size'] ?? null,
                    'target_size' => $details['target_size'] ?? null,
                    'source_checksum' => $details['source_checksum'] ?? null,
                    'target_checksum' => $details['target_checksum'] ?? null,
                ]);
            }

            return $operation;
        });

        foreach ($operation->items()->pluck('id') as $itemId) {
            ProcessMediaOperationItem::dispatch((int) $itemId);
        }

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
            ->filter(fn (MediaOperationItem $item): bool => ! isset($referencedPaths[$item->path])
                && Storage::disk((string) $optimization->source_disk)->exists($item->path))
            ->values();
    }

    private function assertNoOperationRunning(): void
    {
        if (MediaOperation::query()->whereIn('status', [
            MediaOperation::StatusPending,
            MediaOperation::StatusRunning,
        ])->exists()) {
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
    }
}
