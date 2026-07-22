<?php

use App\Filament\Pages\ManageCoverThumbnails;
use App\Models\Game;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media;
use App\Support\MediaThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(Media::diskName());
});

test('administrators can view the cover thumbnails settings page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ManageCoverThumbnails::getUrl(panel: 'admin'))
        ->assertOk();
});

test('administrators can save cover thumbnail size and quality', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCoverThumbnails::class)
        ->fillForm([
            'cover_thumbnail_max_width' => 400,
            'cover_thumbnail_quality' => 70,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::coverThumbnailMaxWidth())->toBe(400)
        ->and(Setting::coverThumbnailQuality())->toBe(70)
        ->and(MediaThumbnail::maxWidth())->toBe(400)
        ->and(MediaThumbnail::quality())->toBe(70);
});

test('thumbnail generation uses the configured max width', function () {
    Setting::set('cover_thumbnail_max_width', '320');

    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 800)
        ->store('games/covers', Media::diskName());

    $thumbnailPath = MediaThumbnail::generate($path);

    expect($thumbnailPath)->toBe(MediaThumbnail::pathFor($path))
        ->and(Media::disk()->exists($thumbnailPath))->toBeTrue();

    $binary = Media::disk()->get($thumbnailPath);
    $size = getimagesizefromstring($binary);

    expect($size[0])->toBe(320)
        ->and($size[1])->toBe(200);
});

test('administrators can regenerate cover thumbnails from settings', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', Media::diskName());

    Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]));

    $thumbnailPath = MediaThumbnail::pathFor($path);

    expect(Media::disk()->exists($thumbnailPath))->toBeFalse();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCoverThumbnails::class)
        ->call('regenerateCoverThumbnails')
        ->assertNotified();

    expect(Media::disk()->exists($thumbnailPath))->toBeTrue();
});

test('regenerating after a size change overwrites existing thumbnails', function () {
    $path = UploadedFile::fake()
        ->image('cover.jpg', 1600, 1000)
        ->store('games/covers', Media::diskName());

    MediaThumbnail::generate($path, maxWidth: 560);
    $thumbnailPath = MediaThumbnail::pathFor($path);

    expect(getimagesizefromstring(Media::disk()->get($thumbnailPath))[0])->toBe(560);

    Game::withoutEvents(fn () => Game::factory()->create([
        'cover_path' => $path,
        'cover_url' => '',
    ]));

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCoverThumbnails::class)
        ->fillForm([
            'cover_thumbnail_max_width' => 400,
            'cover_thumbnail_quality' => Setting::DEFAULT_COVER_THUMBNAIL_QUALITY,
        ])
        ->call('regenerateCoverThumbnails')
        ->assertNotified();

    expect(Setting::coverThumbnailMaxWidth())->toBe(400)
        ->and(getimagesizefromstring(Media::disk()->get($thumbnailPath))[0])->toBe(400);
});

test('regular users cannot access cover thumbnail settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(ManageCoverThumbnails::getUrl(panel: 'admin'))
        ->assertForbidden();
});
