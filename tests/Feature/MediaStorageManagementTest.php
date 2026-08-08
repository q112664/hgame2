<?php

use App\Filament\Pages\ManageMediaStorage;
use App\Filesystem\R2FilesystemAdapter;
use App\Jobs\ProcessMediaOperationItem;
use App\Models\Doc;
use App\Models\Game;
use App\Models\GameRelease;
use App\Models\GameScreenshot;
use App\Models\MediaOperation;
use App\Models\MediaOperationItem;
use App\Models\MediaStorageConfiguration;
use App\Models\Setting;
use App\Models\User;
use App\Support\MediaImageOptimizer;
use App\Support\MediaPathCollector;
use App\Support\MediaReferenceRewriter;
use App\Support\MediaStorageManager;
use App\Support\MediaThumbnail;
use App\Support\MediaUpload;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3ClientInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('r2');
    config([
        'filesystems.media' => 'public',
        'filesystems.disks.r2.url' => 'https://media.example.com',
    ]);
    Http::fake(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response($query['media_healthcheck'] ?? '', 200);
    });
});

test('r2 credentials are encrypted and blank secrets keep the previous value', function (): void {
    $manager = app(MediaStorageManager::class);
    $configuration = $manager->saveConfiguration([
        'account_id' => 'account-id',
        'access_key_id' => 'access-key',
        'secret_access_key' => 'secret-key',
        'bucket' => 'media-bucket',
        'public_url' => 'https://media.example.com',
    ]);

    $raw = MediaStorageConfiguration::query()->whereKey($configuration)->firstOrFail()->getRawOriginal();

    expect($raw['account_id'])->not->toContain('account-id')
        ->and($raw['access_key_id'])->not->toContain('access-key')
        ->and($raw['secret_access_key'])->not->toContain('secret-key')
        ->and($configuration->toArray())->not->toHaveKeys([
            'account_id',
            'access_key_id',
            'secret_access_key',
        ]);

    $unchanged = $manager->saveConfiguration([
        'account_id' => 'account-id',
        'access_key_id' => 'access-key',
        'secret_access_key' => '',
        'bucket' => 'media-bucket',
        'public_url' => 'https://media.example.com',
    ], $configuration);

    expect($unchanged->is($configuration))->toBeTrue()
        ->and($unchanged->secret_access_key)->toBe('secret-key');
});

test('administrators can view media storage while regular users are denied', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ManageMediaStorage::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('Media storage');

    auth()->logout();

    $this->actingAs(User::factory()->create())
        ->get(ManageMediaStorage::getUrl(panel: 'admin'))
        ->assertForbidden();
});

test('saving media storage page creates a candidate without activating r2', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageMediaStorage::class)
        ->fillForm([
            'account_id' => 'account-id',
            'access_key_id' => 'access-key',
            'secret_access_key' => 'secret-key',
            'bucket' => 'media-bucket',
            'public_url' => 'https://media.example.com',
            'region' => 'auto',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $configuration = MediaStorageConfiguration::current();

    expect($configuration)->not->toBeNull()
        ->and($configuration->is_active)->toBeFalse()
        ->and($configuration->wasSuccessfullyTested())->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public');
});

test('media storage page tests the saved configuration before queuing migration', function (): void {
    Queue::fake();
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Storage::disk('public')->put(MediaThumbnail::pathFor('games/covers/one.jpg'), 'thumb');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(ManageMediaStorage::class)
        ->fillForm([
            'account_id' => 'account-id',
            'access_key_id' => 'access-key',
            'secret_access_key' => 'secret-key',
            'bucket' => 'media-bucket',
            'public_url' => 'https://media.example.com',
            'region' => 'auto',
        ])
        ->call('save')
        ->call('startMigration')
        ->assertNotified();

    expect(MediaOperation::query()->count())->toBe(0);

    $component
        ->call('testConnection')
        ->assertNotified()
        ->call('startMigration')
        ->assertNotified();

    expect(MediaStorageConfiguration::current()?->wasSuccessfullyTested())->toBeTrue()
        ->and(MediaOperation::query()->where('type', MediaOperation::TypeMigration)->count())->toBe(1)
        ->and(config('filesystems.media'))->toBe('public');

    Queue::assertPushed(ProcessMediaOperationItem::class, 2);
});

test('connection test verifies upload read and delete without activating r2', function (): void {
    $configuration = createTestedMediaConfiguration(tested: false);

    app(MediaStorageManager::class)->testConnection($configuration);

    expect($configuration->refresh()->wasSuccessfullyTested())->toBeTrue()
        ->and($configuration->is_active)->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public')
        ->and(Storage::disk('r2')->allFiles())->toBeEmpty();
});

test('connection test rejects a public url that cannot read the test object', function (): void {
    Http::swap(new Factory);
    Http::fake(fn () => Http::response('wrong-body', 200));
    $configuration = createTestedMediaConfiguration(tested: false);

    expect(fn () => app(MediaStorageManager::class)->testConnection($configuration))
        ->toThrow(RuntimeException::class, 'public URL');

    expect($configuration->refresh()->wasSuccessfullyTested())->toBeFalse()
        ->and(Storage::disk('r2')->allFiles())->toBeEmpty();
});

test('runtime configuration clears candidate r2 credentials when no configuration is active', function (): void {
    $configuration = createTestedMediaConfiguration();
    $manager = app(MediaStorageManager::class);

    $manager->configureR2($configuration);
    $manager->applyRuntimeConfiguration();

    expect(config('filesystems.media'))->toBe('public')
        ->and(config('filesystems.disks.r2.key'))->toBeNull()
        ->and(config('filesystems.disks.r2.secret'))->toBeNull()
        ->and(config('filesystems.disks.r2.bucket'))->toBeNull()
        ->and(config('filesystems.disks.r2.url'))->toBeNull()
        ->and(config('filesystems.disks.r2.endpoint'))->toBeNull()
        ->and(config('filesystems.disks.r2.configuration_fingerprint'))->toBeNull();
});

test('media operation jobs use a timeout shorter than queue retry windows and fail on timeout', function (): void {
    $job = new ProcessMediaOperationItem(1);

    expect($job->timeout)->toBe(75)
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'))
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.redis.retry_after'))
        ->and($job->failOnTimeout)->toBeTrue();
});

test('r2 adapter removes object acl options from uploads', function (): void {
    $client = Mockery::mock(S3ClientInterface::class);
    $client->shouldAllowMockingMethod('putObject');
    $client->shouldReceive('putObject')
        ->once()
        ->with(Mockery::on(function (array $arguments): bool {
            expect($arguments)
                ->not->toHaveKey('ACL')
                ->not->toHaveKey('GrantRead')
                ->and($arguments['Bucket'])->toBe('media-bucket')
                ->and($arguments['Key'])->toBe('prefix/games/covers/test.webp')
                ->and($arguments['CacheControl'])->toBe('public, max-age=3600');

            return true;
        }))
        ->andReturn(new Result);

    $adapter = new R2FilesystemAdapter($client, 'media-bucket', 'prefix');
    $adapter->write('games/covers/test.webp', 'webp-bytes', new Config([
        'visibility' => 'public',
        'ACL' => 'public-read',
        'GrantRead' => 'everyone',
        'CacheControl' => 'public, max-age=3600',
    ]));
});

test('r2 adapter can read object metadata for size verification', function (): void {
    $client = Mockery::mock(S3ClientInterface::class);
    $command = Mockery::mock(CommandInterface::class);
    $client->shouldReceive('getCommand')
        ->once()
        ->with('HeadObject', Mockery::on(function (array $arguments): bool {
            expect($arguments)
                ->toHaveKey('Bucket', 'media-bucket')
                ->toHaveKey('Key', 'prefix/games/covers/test.webp');

            return true;
        }))
        ->andReturn($command);
    $client->shouldReceive('execute')
        ->once()
        ->with($command)
        ->andReturn(new Result([
            'ContentLength' => 10,
            'ContentType' => 'image/webp',
            'ETag' => '"test-etag"',
        ]));

    $attributes = (new R2FilesystemAdapter($client, 'media-bucket', 'prefix'))
        ->fileSize('games/covers/test.webp');

    expect($attributes->fileSize())->toBe(10)
        ->and($attributes->mimeType())->toBe('image/webp')
        ->and($attributes->extraMetadata())->toHaveKey('ETag', '"test-etag"');
});

test('new r2 uploads keep an identical local rollback copy', function (): void {
    config(['filesystems.media' => 'r2']);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    $path = app(MediaUpload::class)->storeBinary(
        $png,
        'image/png',
        'games/covers',
        'r2',
    );

    expect($path)->toEndWith('.webp')
        ->and(Storage::disk('r2')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->get($path))->toBe(Storage::disk('r2')->get($path));
});

test('migration is blocked when a required cover thumbnail is missing locally', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);

    expect(fn () => app(MediaStorageManager::class)->startMigration($configuration))
        ->toThrow(RuntimeException::class, 'cover thumbnails');
});

test('migration and validation copy every managed media reference and keep local sources', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    $migration = $manager->startMigration($configuration);

    Queue::assertPushed(ProcessMediaOperationItem::class, 9);
    runMediaOperation($migration);

    expect($migration->refresh()->status)->toBe(MediaOperation::StatusCompleted)
        ->and($migration->failed_items)->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(9)
        ->and(Storage::disk('r2')->allFiles())->toHaveCount(9);

    $validation = $manager->startValidation($configuration);
    runMediaOperation($validation);

    expect($validation->refresh()->status)->toBe(MediaOperation::StatusCompleted)
        ->and($validation->failed_items)->toBe(0)
        ->and($validation->items()->whereColumn('source_checksum', 'target_checksum')->count())->toBe(9);
});

test('validation path comparison ignores database collation order', function (): void {
    $manager = app(MediaStorageManager::class);
    $samePathSet = Closure::bind(
        fn (array $left, array $right): bool => $this->samePathSet($left, $right),
        $manager,
        MediaStorageManager::class,
    );

    expect($samePathSet(['games/covers/z.jpg', 'games/covers/a.jpg'], ['games/covers/a.jpg', 'games/covers/z.jpg']))->toBeTrue()
        ->and($samePathSet(['games/covers/a.jpg'], ['games/covers/z.jpg']))->toBeFalse();
});

test('candidate operation jobs restore the active r2 configuration after completion', function (): void {
    Queue::fake();
    $active = createTestedMediaConfiguration(publicUrl: 'https://active-media.example.com');
    $active->forceFill(['is_active' => true, 'activated_at' => now()])->save();
    $candidate = createTestedMediaConfiguration(publicUrl: 'https://candidate-media.example.com');
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Storage::disk('public')->put(MediaThumbnail::pathFor('games/covers/one.jpg'), 'thumb');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);
    $manager = app(MediaStorageManager::class);
    $manager->applyRuntimeConfiguration();
    $operation = $manager->startMigration($candidate);
    $item = $operation->items()->firstOrFail();

    (new ProcessMediaOperationItem((int) $item->getKey()))->handle(
        $manager,
        app(MediaImageOptimizer::class),
        app(MediaPathCollector::class),
        app(MediaReferenceRewriter::class),
    );

    expect(config('filesystems.media'))->toBe('r2')
        ->and(config('filesystems.disks.r2.url'))->toBe('https://active-media.example.com')
        ->and(config('filesystems.disks.r2.configuration_fingerprint'))->toBe($active->configuration_fingerprint)
        ->and($item->refresh()->status)->toBe(MediaOperationItem::StatusCompleted);
});

test('candidate operation jobs restore the active r2 configuration after failure', function (): void {
    Queue::fake();
    $active = createTestedMediaConfiguration(publicUrl: 'https://active-media.example.com');
    $active->forceFill(['is_active' => true, 'activated_at' => now()])->save();
    $candidate = createTestedMediaConfiguration(publicUrl: 'https://candidate-media.example.com');
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Storage::disk('public')->put(MediaThumbnail::pathFor('games/covers/one.jpg'), 'thumb');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);
    $manager = app(MediaStorageManager::class);
    $manager->applyRuntimeConfiguration();
    $operation = $manager->startMigration($candidate);
    $item = $operation->items()->firstOrFail();
    Storage::disk('public')->delete($item->path);

    expect(fn () => (new ProcessMediaOperationItem((int) $item->getKey()))->handle(
        $manager,
        app(MediaImageOptimizer::class),
        app(MediaPathCollector::class),
        app(MediaReferenceRewriter::class),
    ))->toThrow(RuntimeException::class, 'does not exist');

    expect(config('filesystems.media'))->toBe('r2')
        ->and(config('filesystems.disks.r2.url'))->toBe('https://active-media.example.com')
        ->and(config('filesystems.disks.r2.configuration_fingerprint'))->toBe($active->configuration_fingerprint)
        ->and($item->refresh()->status)->toBe(MediaOperationItem::StatusFailed);
});

test('r2 activation requires current validation and rollback keeps local media available', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    $models = seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($configuration));
    runMediaOperation($manager->startValidation($configuration));
    $manager->activate($configuration);

    expect($configuration->refresh()->is_active)->toBeTrue()
        ->and(config('filesystems.media'))->toBe('r2')
        ->and($models['game']->refresh()->description)->toContain('https://media.example.com/games/content/game.jpg')
        ->and($models['release']->refresh()->description)->toContain('https://media.example.com/games/content/release.jpg')
        ->and($models['doc']->refresh()->body)->toContain('https://media.example.com/docs/content/doc.jpg')
        ->and(Setting::get('resource_notice_content'))->toContain('https://media.example.com/site/notices/notice.jpg')
        ->and(Storage::disk('public')->allFiles())->toHaveCount(9);

    $manager->rollbackToLocal($configuration);

    expect($configuration->refresh()->is_active)->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public')
        ->and($models['game']->refresh()->description)->toContain('/storage/games/content/game.jpg')
        ->and($models['release']->refresh()->description)->toContain('/storage/games/content/release.jpg')
        ->and($models['doc']->refresh()->body)->toContain('/storage/docs/content/doc.jpg')
        ->and(Setting::get('resource_notice_content'))->toContain('/storage/site/notices/notice.jpg');
});

test('activating a replacement r2 configuration rewrites the previous cdn domain', function (): void {
    Queue::fake();
    $first = createTestedMediaConfiguration(publicUrl: 'https://old-media.example.com');
    $models = seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($first));
    runMediaOperation($manager->startValidation($first));
    $manager->activate($first);

    $second = createTestedMediaConfiguration(publicUrl: 'https://new-media.example.com');
    runMediaOperation($manager->startMigration($second));
    runMediaOperation($manager->startValidation($second));
    $manager->activate($second);

    expect($first->refresh()->is_active)->toBeFalse()
        ->and($second->refresh()->is_active)->toBeTrue()
        ->and($models['game']->refresh()->description)->toContain('https://new-media.example.com/games/content/game.jpg')
        ->and($models['game']->description)->not->toContain('https://old-media.example.com/')
        ->and($models['release']->refresh()->description)->toContain('https://new-media.example.com/games/content/release.jpg')
        ->and($models['doc']->refresh()->body)->toContain('https://new-media.example.com/docs/content/doc.jpg')
        ->and(Setting::get('resource_notice_content'))->toContain('https://new-media.example.com/site/notices/notice.jpg');
});

test('activation is blocked when local media changes after validation', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($configuration));
    runMediaOperation($manager->startValidation($configuration));
    Storage::disk('public')->put('games/covers/cover.jpg', 'changed');

    expect(fn () => $manager->activate($configuration))
        ->toThrow(RuntimeException::class, 'changed after validation');

    expect($configuration->refresh()->is_active)->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public');
});

test('activation revalidates r2 objects before rewriting references', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($configuration));
    runMediaOperation($manager->startValidation($configuration));
    Storage::disk('r2')->delete('games/content/game.jpg');

    expect(fn () => $manager->activate($configuration))
        ->toThrow(RuntimeException::class, 'missing before activation');

    expect($configuration->refresh()->is_active)->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public');
});

test('activation requires the r2 public url to serve a validated object', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($configuration));
    runMediaOperation($manager->startValidation($configuration));
    Http::swap(new Factory);
    Http::fake(fn () => Http::response('', 503));

    expect(fn () => $manager->activate($configuration))
        ->toThrow(RuntimeException::class, 'public URL');

    expect($configuration->refresh()->is_active)->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public');
});

test('rollback rejects a same-size corrupted local media copy', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($configuration));
    runMediaOperation($manager->startValidation($configuration));
    $manager->activate($configuration);

    $path = 'games/content/game.jpg';
    $original = Storage::disk('public')->get($path);
    Storage::disk('public')->put($path, str_repeat('x', strlen($original)));

    expect(fn () => $manager->rollbackToLocal($configuration))
        ->toThrow(RuntimeException::class, 'changed after validation');

    expect($configuration->refresh()->is_active)->toBeTrue()
        ->and(config('filesystems.media'))->toBe('r2');
});

test('rollback accepts media uploaded after activation when local and r2 copies match', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    $models = seedManagedMediaReferences();
    $manager = app(MediaStorageManager::class);

    runMediaOperation($manager->startMigration($configuration));
    runMediaOperation($manager->startValidation($configuration));
    $manager->activate($configuration);

    $latePath = app(MediaUpload::class)->storeBinary(
        'late-media',
        'image/gif',
        'games/content',
        'r2',
    );
    $models['game']->forceFill([
        'description' => '<p><img src="/storage/'.$latePath.'"></p>',
    ])->saveQuietly();

    $manager->rollbackToLocal($configuration);

    expect($configuration->refresh()->is_active)->toBeFalse()
        ->and(config('filesystems.media'))->toBe('public')
        ->and($models['game']->refresh()->description)->toContain('/storage/'.$latePath)
        ->and(Storage::disk('public')->get($latePath))->toBe('late-media');
});

test('expired media operation leases are recovered and old queue tokens are rejected', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Storage::disk('public')->put(MediaThumbnail::pathFor('games/covers/one.jpg'), 'thumb');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);
    $operation = app(MediaStorageManager::class)->startMigration($configuration);
    $item = $operation->items()->firstOrFail();
    $oldToken = (string) $item->dispatch_token;

    $item->forceFill([
        'status' => MediaOperationItem::StatusRunning,
        'lease_token' => $oldToken,
        'lease_expires_at' => now()->subSecond(),
        'attempts' => 1,
    ])->save();

    Artisan::call('media:recover-operations');

    expect($item->refresh()->status)->toBe(MediaOperationItem::StatusPending)
        ->and($item->dispatch_token)->not->toBe($oldToken);

    Queue::assertPushed(ProcessMediaOperationItem::class, function (ProcessMediaOperationItem $job) use ($item, $oldToken): bool {
        return $job->itemId === $item->id && $job->dispatchToken !== $oldToken;
    });
});

test('legacy running items without a lease are recovered after the grace period', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Storage::disk('public')->put(MediaThumbnail::pathFor('games/covers/one.jpg'), 'thumb');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);
    $operation = app(MediaStorageManager::class)->startMigration($configuration);
    $item = $operation->items()->firstOrFail();
    $oldToken = (string) $item->dispatch_token;

    MediaOperationItem::query()->whereKey($item)->update([
        'status' => MediaOperationItem::StatusRunning,
        'lease_token' => null,
        'lease_expires_at' => null,
        'attempts' => 1,
        'updated_at' => now()->subMinutes(6),
    ]);
    Queue::fake();

    Artisan::call('media:recover-operations');

    expect($item->refresh()->status)->toBe(MediaOperationItem::StatusPending)
        ->and($item->dispatch_token)->not->toBe($oldToken);

    Queue::assertPushed(ProcessMediaOperationItem::class, function (ProcessMediaOperationItem $job) use ($item, $oldToken): bool {
        return $job->itemId === $item->id && $job->dispatchToken !== $oldToken;
    });
});

test('retrying failed operation items atomically resets their attempt budget', function (): void {
    Queue::fake();
    $configuration = createTestedMediaConfiguration();
    Storage::disk('public')->put('games/covers/one.jpg', 'one');
    Storage::disk('public')->put(MediaThumbnail::pathFor('games/covers/one.jpg'), 'thumb');
    Game::factory()->create(['cover_path' => 'games/covers/one.jpg']);
    $manager = app(MediaStorageManager::class);
    $operation = $manager->startMigration($configuration);
    $failedItem = $operation->items()->firstOrFail();

    $operation->items()->whereKeyNot([$failedItem->id])->update([
        'status' => MediaOperationItem::StatusCompleted,
        'completed_at' => now(),
    ]);
    $failedItem->forceFill([
        'status' => MediaOperationItem::StatusFailed,
        'attempts' => ProcessMediaOperationItem::MaxAttempts,
        'completed_at' => now(),
        'lease_token' => null,
        'lease_expires_at' => null,
    ])->save();
    $manager->refreshOperationProgress($operation);
    Queue::fake();

    $retried = $manager->retryFailed($operation->refresh());

    expect($retried->status)->toBe(MediaOperation::StatusRunning)
        ->and($retried->running_slot)->toBe(1)
        ->and($failedItem->refresh()->status)->toBe(MediaOperationItem::StatusPending)
        ->and($failedItem->attempts)->toBe(0)
        ->and($failedItem->dispatch_token)->not->toBeNull();

    Queue::assertPushed(ProcessMediaOperationItem::class, fn (ProcessMediaOperationItem $job): bool => $job->itemId === $failedItem->id);
});

test('public media urls with a path prefix are rejected before storage is tested', function (): void {
    expect(fn () => app(MediaStorageManager::class)->saveConfiguration([
        'account_id' => 'account-id',
        'access_key_id' => 'access-key',
        'secret_access_key' => 'secret-key',
        'bucket' => 'media-bucket',
        'public_url' => 'https://media.example.com/assets',
    ]))->toThrow(InvalidArgumentException::class, 'path prefix');
});

function createTestedMediaConfiguration(
    bool $tested = true,
    string $publicUrl = 'https://media.example.com',
): MediaStorageConfiguration {
    $configuration = app(MediaStorageManager::class)->saveConfiguration([
        'account_id' => 'account-id',
        'access_key_id' => 'access-key',
        'secret_access_key' => 'secret-key',
        'bucket' => 'media-bucket',
        'public_url' => $publicUrl,
    ]);

    if ($tested) {
        $configuration->forceFill([
            'tested_fingerprint' => $configuration->configuration_fingerprint,
            'connection_tested_at' => now(),
        ])->save();
    }

    return $configuration;
}

/** @return array{game: Game, release: GameRelease, doc: Doc, user: User} */
function seedManagedMediaReferences(): array
{
    $files = [
        'games/covers/cover.jpg' => 'cover',
        'games/covers/thumbs/cover.webp' => 'cover-thumb',
        'games/screenshots/shot.jpg' => 'screenshot',
        'games/content/game.jpg' => 'game-content',
        'games/content/release.jpg' => 'release-content',
        'docs/covers/doc.jpg' => 'doc-cover',
        'docs/content/doc.jpg' => 'doc-content',
        'avatars/user.jpg' => 'avatar',
        'site/notices/notice.jpg' => 'notice',
    ];

    foreach ($files as $path => $contents) {
        Storage::disk('public')->put($path, $contents);
    }

    $game = Game::factory()->create([
        'cover_path' => 'games/covers/cover.jpg',
        'description' => '<p><img src="/storage/games/content/game.jpg"></p>',
    ]);
    GameScreenshot::factory()->for($game)->create(['path' => 'games/screenshots/shot.jpg']);
    $release = GameRelease::factory()->for($game)->create([
        'description' => '<p><img src="/storage/games/content/release.jpg"></p>',
    ]);
    $doc = Doc::factory()->create([
        'cover_path' => 'docs/covers/doc.jpg',
        'body' => '<p><img src="/storage/docs/content/doc.jpg"></p>',
    ]);
    $user = User::factory()->create(['avatar' => 'avatars/user.jpg']);
    Setting::set('resource_notice_content', '<p><img src="/storage/site/notices/notice.jpg"></p>');

    return compact('game', 'release', 'doc', 'user');
}

function runMediaOperation(MediaOperation $operation): void
{
    $operation->items()->orderBy('id')->each(function (MediaOperationItem $item): void {
        (new ProcessMediaOperationItem((int) $item->getKey()))->handle(
            app(MediaStorageManager::class),
            app(MediaImageOptimizer::class),
            app(MediaPathCollector::class),
            app(MediaReferenceRewriter::class),
        );
    });
}
