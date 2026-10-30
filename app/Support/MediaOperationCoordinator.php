<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class MediaOperationCoordinator
{
    private const OperationLock = 'media-storage:operation-transition';

    private const CutoverLock = 'media-storage:cutover';

    /** Long-running storage migrations: keep the lock for the whole operation. */
    private const OperationLockSeconds = 900;

    /**
     * File writes are sub-second, so a crashed request must not block uploads and
     * saves for a quarter of an hour; the lock expires quickly instead.
     */
    private const CutoverLockSeconds = 120;

    private const WaitSeconds = 5;

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function operation(\Closure $callback): mixed
    {
        return $this->run(self::OperationLock, self::OperationLockSeconds, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function cutover(\Closure $callback): mixed
    {
        return $this->run(self::CutoverLock, self::CutoverLockSeconds, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function run(string $name, int $seconds, \Closure $callback): mixed
    {
        $lock = Cache::lock($name, $seconds);

        try {
            $lock->block(self::WaitSeconds);
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException(
                'Another media storage transition is already in progress.',
                previous: $exception,
            );
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
