<?php

use App\Support\MediaOperationCoordinator;
use Illuminate\Support\Facades\Cache;

test('the cutover lock is released when the operation fails', function () {
    $coordinator = app(MediaOperationCoordinator::class);

    expect(fn () => $coordinator->cutover(fn () => throw new RuntimeException('disk write failed')))
        ->toThrow(RuntimeException::class, 'disk write failed');

    // A leaked lock would make the next upload or save fail with "already in progress".
    $lock = Cache::lock('media-storage:cutover', 5);

    expect($lock->get())->toBeTrue();

    $lock->release();
});

test('a second cutover waits instead of throwing immediately', function () {
    $coordinator = app(MediaOperationCoordinator::class);

    $first = Cache::lock('media-storage:cutover', 30);
    expect($first->get())->toBeTrue();

    try {
        expect(fn () => $coordinator->cutover(fn () => 'never'))
            ->toThrow(RuntimeException::class, 'Another media storage transition is already in progress.');
    } finally {
        $first->release();
    }
});

test('cutover locks expire far sooner than migration locks', function () {
    $reflection = new ReflectionClass(MediaOperationCoordinator::class);

    expect($reflection->getConstant('CutoverLockSeconds'))->toBe(120)
        ->and($reflection->getConstant('OperationLockSeconds'))->toBe(900);
});
