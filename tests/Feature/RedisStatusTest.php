<?php

use App\Filament\Pages\ViewRedisStatus;
use App\Models\User;
use App\Support\RedisStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('redis status is hidden when redis drivers are not configured', function () {
    config([
        'cache.default' => 'array',
        'queue.default' => 'sync',
        'session.driver' => 'array',
    ]);

    expect(RedisStatus::isConfigured())->toBeFalse()
        ->and(ViewRedisStatus::shouldRegisterNavigation())->toBeFalse()
        ->and(ViewRedisStatus::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->admin()->create())
        ->get(ViewRedisStatus::getUrl(panel: 'admin'))
        ->assertForbidden();
});

test('redis status page is available when redis is configured', function () {
    // Use queue (not cache) so app Setting lookups keep working on the array cache driver.
    config([
        'queue.default' => 'redis',
        'session.driver' => 'array',
    ]);

    expect(RedisStatus::isConfigured())->toBeTrue()
        ->and(ViewRedisStatus::shouldRegisterNavigation())->toBeTrue();

    // Snapshot may fail to connect without a Redis server; the page should still render.
    $this->actingAs(User::factory()->admin()->create())
        ->get(ViewRedisStatus::getUrl(panel: 'admin'))
        ->assertOk();
});

test('redis snapshot reports driver configuration', function () {
    config([
        'queue.default' => 'redis',
        'session.driver' => 'redis',
    ]);

    $snapshot = RedisStatus::snapshot();

    expect($snapshot['configured'])->toBeTrue()
        ->and($snapshot['drivers']['queue'])->toBe('redis')
        ->and($snapshot['drivers']['session'])->toBe('redis');
});
