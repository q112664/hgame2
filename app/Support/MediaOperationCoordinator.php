<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class MediaOperationCoordinator
{
    private const OperationLock = 'media-storage:operation-transition';

    private const CutoverLock = 'media-storage:cutover';

    private const LockSeconds = 900;

    private const WaitSeconds = 5;

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function operation(\Closure $callback): mixed
    {
        return $this->run(self::OperationLock, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function cutover(\Closure $callback): mixed
    {
        return $this->run(self::CutoverLock, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function run(string $name, \Closure $callback): mixed
    {
        $lock = Cache::lock($name, self::LockSeconds);

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
