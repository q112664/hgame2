<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * @property int $id
 * @property string $provider
 * @property string $account_id
 * @property string $access_key_id
 * @property string $secret_access_key
 * @property string $bucket
 * @property string $public_url
 * @property string $region
 * @property string $configuration_fingerprint
 * @property string|null $tested_fingerprint
 * @property CarbonImmutable|null $connection_tested_at
 * @property string|null $connection_test_error
 * @property bool $is_active
 * @property CarbonImmutable|null $activated_at
 */
#[Fillable([
    'provider',
    'account_id',
    'access_key_id',
    'secret_access_key',
    'bucket',
    'public_url',
    'region',
    'configuration_fingerprint',
    'tested_fingerprint',
    'connection_tested_at',
    'connection_test_error',
    'is_active',
    'activated_at',
])]
class MediaStorageConfiguration extends Model
{
    /** @var list<string> */
    protected $hidden = [
        'account_id',
        'access_key_id',
        'secret_access_key',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'encrypted',
            'access_key_id' => 'encrypted',
            'secret_access_key' => 'encrypted',
            'connection_tested_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'activated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<MediaOperation, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(MediaOperation::class);
    }

    public function endpoint(): string
    {
        return 'https://'.trim((string) $this->account_id).'.r2.cloudflarestorage.com';
    }

    public function wasSuccessfullyTested(): bool
    {
        return filled($this->tested_fingerprint)
            && hash_equals((string) $this->configuration_fingerprint, (string) $this->tested_fingerprint)
            && $this->connection_tested_at !== null
            && blank($this->connection_test_error);
    }

    public static function current(): ?self
    {
        if (! self::tableReady()) {
            return null;
        }

        return self::query()->latest('id')->first();
    }

    public static function active(): ?self
    {
        if (! self::tableReady()) {
            return null;
        }

        return self::query()->where('is_active', true)->latest('id')->first();
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable((new self)->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
