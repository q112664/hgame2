<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $media_storage_configuration_id
 * @property int|null $user_id
 * @property string $type
 * @property string $status
 * @property string|null $source_disk
 * @property string|null $target_disk
 * @property string|null $configuration_fingerprint
 * @property int $total_items
 * @property int $processed_items
 * @property int $succeeded_items
 * @property int $skipped_items
 * @property int $failed_items
 * @property int $total_source_bytes
 * @property int $total_target_bytes
 * @property array<string, mixed>|null $metadata
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'media_storage_configuration_id',
    'user_id',
    'type',
    'status',
    'source_disk',
    'target_disk',
    'configuration_fingerprint',
    'total_items',
    'processed_items',
    'succeeded_items',
    'skipped_items',
    'failed_items',
    'total_source_bytes',
    'total_target_bytes',
    'metadata',
    'error',
    'started_at',
    'completed_at',
])]
class MediaOperation extends Model
{
    public const string TypeMigration = 'migration';

    public const string TypeValidation = 'validation';

    public const string TypeOptimization = 'optimization';

    public const string StatusPending = 'pending';

    public const string StatusRunning = 'running';

    public const string StatusCompleted = 'completed';

    public const string StatusFailed = 'failed';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MediaStorageConfiguration, $this> */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(MediaStorageConfiguration::class, 'media_storage_configuration_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<MediaOperationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MediaOperationItem::class);
    }

    public function progressPercentage(): int
    {
        if ($this->total_items === 0) {
            return $this->status === self::StatusCompleted ? 100 : 0;
        }

        return (int) min(100, round(($this->processed_items / $this->total_items) * 100));
    }
}
