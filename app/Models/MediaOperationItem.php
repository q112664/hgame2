<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $media_operation_id
 * @property string $path
 * @property string $path_hash
 * @property string|null $target_path
 * @property string $status
 * @property int $attempts
 * @property string|null $dispatch_token
 * @property CarbonImmutable|null $dispatched_at
 * @property string|null $lease_token
 * @property CarbonImmutable|null $lease_expires_at
 * @property CarbonImmutable|null $heartbeat_at
 * @property int|null $source_size
 * @property int|null $target_size
 * @property string|null $source_checksum
 * @property string|null $target_checksum
 * @property string|null $remote_etag
 * @property string|null $remote_version_id
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable([
    'media_operation_id',
    'path',
    'path_hash',
    'target_path',
    'target_path_hash',
    'status',
    'attempts',
    'dispatch_token',
    'dispatched_at',
    'lease_token',
    'lease_expires_at',
    'heartbeat_at',
    'source_size',
    'target_size',
    'source_checksum',
    'target_checksum',
    'remote_etag',
    'remote_version_id',
    'error',
    'started_at',
    'completed_at',
])]
class MediaOperationItem extends Model
{
    public const string StatusPending = 'pending';

    public const string StatusRunning = 'running';

    public const string StatusCompleted = 'completed';

    public const string StatusSkipped = 'skipped';

    public const string StatusFailed = 'failed';

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
            'heartbeat_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MediaOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(MediaOperation::class, 'media_operation_id');
    }
}
