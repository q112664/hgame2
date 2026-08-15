<?php

use App\Filament\Resources\ResourceSources\Pages\ManageResourceSources;
use App\Filament\Resources\ResourceSources\ResourceSourceResource;
use App\Models\ResourceSource;
use App\Models\User;
use App\Support\GameSource;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrators can open the sources manager', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ResourceSourceResource::getUrl(panel: 'admin'))
        ->assertOk();
});

test('administrators can create and delete a reusable source', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageResourceSources::class)
        ->callAction('create', [
            'name' => 'Booth',
            'slug' => 'booth',
            'icon_path' => UploadedFile::fake()->image('booth.png', 64, 64),
            'host_hint' => 'booth.pm',
            'sort_order' => 5,
        ])
        ->assertHasNoActionErrors();

    $source = ResourceSource::query()->where('slug', 'booth')->first();

    expect($source)->not->toBeNull()
        ->and(GameSource::options())->toHaveKey('Booth');

    Livewire::test(ManageResourceSources::class)
        ->callTableAction('delete', $source)
        ->assertHasNoTableActionErrors();

    expect(ResourceSource::query()->where('slug', 'booth')->exists())->toBeFalse()
        ->and(GameSource::options())->not->toHaveKey('Booth');
});

test('regular users cannot access resource sources', function () {
    $this->actingAs(User::factory()->create())
        ->get(ResourceSourceResource::getUrl(panel: 'admin'))
        ->assertForbidden();
});
