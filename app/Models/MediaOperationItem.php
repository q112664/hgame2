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
 * @property int|null $source_size
 * @property int|null $target_size
 * @property string|null $source_checksum
 * @property string|null $target_checksum
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'media_operation_id',
    'path',
    'path_hash',
    'target_path',
    'status',
    'attempts',
    'source_size',
    'target_size',
    'source_checksum',
    'target_checksum',
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
        ];
    }

    /** @return BelongsTo<MediaOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(MediaOperation::class, 'media_operation_id');
    }
}
