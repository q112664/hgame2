<?php

namespace App\Actions\Media;

use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Support\Media;
use App\Support\MediaPathCollector;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PruneVerifiedR2Originals
{
    public function __construct(private MediaPathCollector $pathCollector) {}

    /**
     * Delete R2 originals that a completed cleanup item already verified.
     *
     * Failed cleanup items are left untouched. The optimized WebP is never deleted.
     *
     * @return array{
     *     operation_id: int,
     *     eligible: int,
     *     deletable: int,
     *     deleted: int,
     *     bytes: int,
     *     skipped: int,
     *     skip_reasons: array<string, int>
     * }
     */
    public function __invoke(?int $operationId = null, bool $force = false): array
    {
        if (Media::diskName() !== 'r2') {
            throw new RuntimeException('R2 is not the active media disk.');
        }

        $operation = $this->cleanupOperation($operationId);
        $referenced = array_flip($this->pathCollector->references());
        $eligible = 0;
        $deletable = 0;
        $deleted = 0;
        $bytes = 0;
        $skipReasons = [];

        $items = $operation->items()
            ->where('status', MediaOperationItem::StatusCompleted)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $eligible++;
            $reason = $this->rejectionReason($item, $referenced);

            if ($reason !== null) {
                $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;

                continue;
            }

            $size = Storage::disk('r2')->size($item->path);
            $deletable++;
            $bytes += $size;

            if (! $force) {
                continue;
            }

            if ($this->pathCollector->isReferenced($item->path)) {
                $deletable--;
                $bytes -= $size;
                $skipReasons['still-referenced'] = ($skipReasons['still-referenced'] ?? 0) + 1;

                continue;
            }

            $disk = Storage::disk('r2');

            if ($disk->delete($item->path) === false || $disk->exists($item->path)) {
                $deletable--;
                $bytes -= $size;
                $skipReasons['delete-failed'] = ($skipReasons['delete-failed'] ?? 0) + 1;

                continue;
            }

            $deleted++;
        }

        ksort($skipReasons);

        return [
            'operation_id' => (int) $operation->getKey(),
            'eligible' => $eligible,
            'deletable' => $deletable,
            'deleted' => $deleted,
            'bytes' => $bytes,
            'skipped' => array_sum($skipReasons),
            'skip_reasons' => $skipReasons,
        ];
    }

    private function cleanupOperation(?int $operationId): MediaOperation
    {
        $query = MediaOperation::query()->where('type', MediaOperation::TypeCleanup);

        $operation = $operationId === null
            ? $query->latest('id')->first()
            : $query->find($operationId);

        if (! $operation instanceof MediaOperation) {
            throw new RuntimeException('No cleanup operation is available.');
        }

        return $operation;
    }

    /**
     * @param  array<string, int>  $referenced
     */
    private function rejectionReason(MediaOperationItem $item, array $referenced): ?string
    {
        $path = $item->path;
        $targetPath = (string) $item->target_path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (
            blank($item->source_checksum)
            || blank($item->target_checksum)
            || $targetPath === ''
            || $targetPath === $path
        ) {
            return 'incomplete';
        }

        if (
            ! in_array($extension, ['jpg', 'jpeg', 'png'], true)
            || strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) !== 'webp'
            || str_contains($path, '/thumbs/')
        ) {
            return 'unsupported-original';
        }

        if (isset($referenced[$path]) || $this->pathCollector->isReferenced($path)) {
            return 'still-referenced';
        }

        if (! isset($referenced[$targetPath]) && ! $this->pathCollector->isReferenced($targetPath)) {
            return 'optimized-file-unused';
        }

        $disk = Storage::disk('r2');

        if (! $disk->exists($path)) {
            return 'missing-original';
        }

        if (! $disk->exists($targetPath)) {
            return 'missing-optimized';
        }

        if ($item->source_size !== null && $disk->size($path) !== (int) $item->source_size) {
            return 'size-mismatch';
        }

        $sourceChecksum = $this->checksum($path);
        $targetChecksum = $this->checksum($targetPath);

        if (
            $sourceChecksum === null
            || $targetChecksum === null
            || strlen($sourceChecksum) !== strlen((string) $item->source_checksum)
            || strlen($targetChecksum) !== strlen((string) $item->target_checksum)
            || ! hash_equals((string) $item->source_checksum, $sourceChecksum)
            || ! hash_equals((string) $item->target_checksum, $targetChecksum)
        ) {
            return 'checksum-mismatch';
        }

        return null;
    }

    private function checksum(string $path): ?string
    {
        $stream = Storage::disk('r2')->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
