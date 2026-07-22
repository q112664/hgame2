<?php

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrators can view the site settings page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ManageSiteSettings::getUrl(panel: 'admin'))
        ->assertOk();
});

test('administrators can update the site url', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => 'http://hgame.test',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::get('site_url'))->toBe('http://hgame.test')
        ->and(config('app.url'))->toBe('http://hgame.test')
        ->and(config('filesystems.disks.public.url'))->toBe('http://hgame.test/storage');
});

test('site url config is applied without forcing the root url in console', function () {
    $forcedRoot = new ReflectionProperty(app('url'), 'forcedRoot');

    Setting::applySiteUrlToConfig('https://acg.example.com');

    expect(config('app.url'))->toBe('https://acg.example.com')
        ->and(config('filesystems.disks.public.url'))->toBe('https://acg.example.com/storage')
        ->and($forcedRoot->getValue(app('url')))->toBeNull();
});

test('https site url forces https scheme', function () {
    Setting::applySiteUrlToConfig('https://acg.example.com');

    $forcedScheme = new ReflectionProperty(app('url'), 'forceScheme');

    expect($forcedScheme->getValue(app('url')))->toBe('https://')
        ->and(config('app.url'))->toBe('https://acg.example.com');
});

test('administrators can upload a custom hero background image', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'hero_background_path' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $path = Setting::heroBackgroundPath();

    expect($path)->not->toBeNull()
        ->and($path)->toStartWith('site/hero/')
        ->and(Setting::heroBackgroundUrl())->toContain('/storage/'.$path);

    Storage::disk(Media::diskName())->assertExists($path);
});

test('clearing the hero background restores the default image url', function () {
    Storage::fake(Media::diskName());

    $previous = UploadedFile::fake()
        ->image('old-hero.jpg', 1600, 900)
        ->store('site/hero', Media::diskName());

    Setting::set('hero_background_path', $previous);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'hero_background_path' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::heroBackgroundPath())->toBeNull()
        ->and(Setting::heroBackgroundUrl())->toBe(Setting::defaultHeroBackgroundUrl())
        ->and(Storage::disk(Media::diskName())->exists($previous))->toBeFalse();
});

test('administrators can save a text-only site logo', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_logo_mode' => 'text',
            'site_logo_text' => 'Archive',
            'site_logo_path' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::siteLogo())->toMatchArray([
        'mode' => 'text',
        'text' => 'Archive',
        'imageUrl' => null,
    ]);
});

test('administrators can save an image-only site logo', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_logo_mode' => 'image',
            'site_logo_text' => 'hgame',
            'site_logo_path' => UploadedFile::fake()->image('logo.png', 128, 128),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $logo = Setting::siteLogo();

    expect($logo['mode'])->toBe('image')
        ->and($logo['imageUrl'])->toContain('/storage/site/logo/')
        ->and(Setting::siteLogoPath())->toStartWith('site/logo/');

    Storage::disk(Media::diskName())->assertExists(Setting::siteLogoPath());
});

test('administrators can save a combined image and text site logo', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_logo_mode' => 'both',
            'site_logo_text' => 'hgame',
            'site_logo_path' => UploadedFile::fake()->image('mark.webp', 96, 96),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $logo = Setting::siteLogo();

    expect($logo['mode'])->toBe('both')
        ->and($logo['text'])->toBe('hgame')
        ->and($logo['imageUrl'])->toContain('/storage/site/logo/');
});

test('site logo is shared with the frontend', function () {
    Setting::set('site_logo_mode', 'text');
    Setting::set('site_logo_text', 'Catalog');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('name', 'Catalog')
            ->where('siteLogo.mode', 'text')
            ->where('siteLogo.text', 'Catalog')
            ->where('siteLogo.imageUrl', null)
        );
});

test('avatar urls use the configured site url', function () {
    Storage::fake(Media::diskName());

    Setting::set('site_url', 'http://hgame.test');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => $file,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->avatar)->toStartWith('http://hgame.test/storage/avatars/');
});

test('regular users cannot access site settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(ManageSiteSettings::getUrl(panel: 'admin'))
        ->assertForbidden();
});
