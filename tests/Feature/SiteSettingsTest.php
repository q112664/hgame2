<?php

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Setting;
use App\Models\User;
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

test('avatar urls use the configured site url', function () {
    Storage::fake('public');

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
