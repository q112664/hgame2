<?php

use App\Actions\Media\MigrateMediaDisk;
use App\Filament\Pages\ManageObjectStorage;
use App\Models\Game;
use App\Models\GameScreenshot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('administrators can view the object storage page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ManageObjectStorage::getUrl(panel: 'admin'))
        ->assertOk();
});

test('administrators can save object storage settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageObjectStorage::class)
        ->fillForm([
            'media_disk' => 's3',
            'aws_access_key_id' => 'testing-key',
            'aws_secret_access_key' => 'testing-secret',
            'aws_default_region' => 'ap-northeast-1',
            'aws_bucket' => 'hgame-media',
            'aws_url' => 'https://cdn.example.com',
            'aws_endpoint' => 'https://s3.example.com',
            'aws_use_path_style_endpoint' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::mediaDisk())->toBe('s3')
        ->and(Setting::get('aws_access_key_id'))->toBe('testing-key')
        ->and(Setting::getDecrypted('aws_secret_access_key'))->toBe('testing-secret')
        ->and(Setting::get('aws_bucket'))->toBe('hgame-media')
        ->and(config('filesystems.media'))->toBe('s3')
        ->and(config('filesystems.disks.s3.key'))->toBe('testing-key')
        ->and(config('filesystems.disks.s3.secret'))->toBe('testing-secret')
        ->and(config('filesystems.disks.s3.bucket'))->toBe('hgame-media')
        ->and(config('filesystems.disks.s3.url'))->toBe('https://cdn.example.com')
        ->and(config('filesystems.disks.s3.use_path_style_endpoint'))->toBeTrue();
});

test('saving s3 settings without a new secret keeps the existing secret', function () {
    Setting::set('media_disk', 's3');
    Setting::set('aws_access_key_id', 'old-key');
    Setting::setEncrypted('aws_secret_access_key', 'keep-me');
    Setting::set('aws_default_region', 'us-east-1');
    Setting::set('aws_bucket', 'bucket');

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageObjectStorage::class)
        ->fillForm([
            'media_disk' => 's3',
            'aws_access_key_id' => 'new-key',
            'aws_secret_access_key' => '',
            'aws_default_region' => 'us-east-1',
            'aws_bucket' => 'bucket',
            'aws_url' => null,
            'aws_endpoint' => null,
            'aws_use_path_style_endpoint' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('aws_access_key_id'))->toBe('new-key')
        ->and(Setting::getDecrypted('aws_secret_access_key'))->toBe('keep-me');
});

test('media files can be migrated from public disk to s3', function () {
    Storage::fake('public');
    Storage::fake('s3');

    Setting::set('site_url', 'http://hgame.test');
    Setting::set('media_disk', 'public');

    $cover = 'games/covers/cover.jpg';
    $screenshot = 'games/screenshots/shot.jpg';
    $avatar = 'avatars/user.jpg';
    $content = 'games/content/embed.jpg';

    Storage::disk('public')->put($cover, 'cover');
    Storage::disk('public')->put($screenshot, 'shot');
    Storage::disk('public')->put($avatar, 'avatar');
    Storage::disk('public')->put($content, 'content');

    $game = Game::factory()->create([
        'cover_path' => $cover,
        'description' => '<p><img src="http://hgame.test/storage/games/content/embed.jpg"></p>',
    ]);

    GameScreenshot::factory()->for($game)->create([
        'path' => $screenshot,
    ]);

    User::factory()->create([
        'avatar' => $avatar,
    ]);

    config([
        'filesystems.media' => 's3',
        'filesystems.disks.s3.url' => 'https://cdn.example.com',
    ]);

    $result = app(MigrateMediaDisk::class)('public', 's3');

    expect($result['migrated'])->toBe(4)
        ->and($result['failed'])->toBe(0)
        ->and(Storage::disk('s3')->get($cover))->toBe('cover')
        ->and(Storage::disk('s3')->get($screenshot))->toBe('shot')
        ->and(Storage::disk('s3')->get($avatar))->toBe('avatar')
        ->and(Storage::disk('s3')->get($content))->toBe('content')
        ->and($game->refresh()->description)->toContain('https://cdn.example.com/games/content/embed.jpg')
        ->and($game->description)->not->toContain('http://hgame.test/storage/');
});

test('administrators can trigger media migration from object storage settings', function () {
    Storage::fake('public');
    Storage::fake('s3');

    Storage::disk('public')->put('games/covers/one.jpg', 'one');

    Game::factory()->create([
        'cover_path' => 'games/covers/one.jpg',
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageObjectStorage::class)
        ->fillForm([
            'media_disk' => 's3',
            'aws_access_key_id' => 'testing-key',
            'aws_secret_access_key' => 'testing-secret',
            'aws_default_region' => 'us-east-1',
            'aws_bucket' => 'hgame-media',
            'aws_url' => 'https://cdn.example.com',
            'aws_endpoint' => null,
            'aws_use_path_style_endpoint' => false,
        ])
        ->call('migrateMedia')
        ->assertNotified();

    expect(Setting::mediaDisk())->toBe('s3')
        ->and(Storage::disk('s3')->exists('games/covers/one.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('games/covers/one.jpg'))->toBeTrue();
});

test('regular users cannot access object storage settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(ManageObjectStorage::getUrl(panel: 'admin'))
        ->assertForbidden();
});
