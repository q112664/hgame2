<?php

use App\Filament\Resources\Games\Pages\CreateGame;
use App\GameStatus;
use App\Models\Game;
use App\Models\Tag;
use App\Models\User;
use App\Support\Media;
use App\Support\TagImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an administrator can create a game with existing tags selected', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    $romance = Tag::factory()->create(['name' => 'Romance', 'slug' => 'romance']);
    $fantasy = Tag::factory()->create(['name' => 'Fantasy', 'slug' => 'fantasy']);

    Livewire::test(CreateGame::class)
        ->fillForm([
            'title' => 'Tagged Game',
            'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'tags' => [$romance->id, $fantasy->id],
            'status' => GameStatus::Draft->value,
            'published_at' => now(),
            'releases' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $game = Game::query()->where('slug', 'tagged-game')->firstOrFail();

    expect($game->tags()->pluck('tags.id')->all())
        ->toEqualCanonicalizing([$romance->id, $fantasy->id]);
});

test('creating tags via importer and selecting them on create does not fail validation', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    $ids = app(TagImporter::class)->import('Slice of Life, Comedy');

    Livewire::test(CreateGame::class)
        ->fillForm([
            'title' => 'Imported Tags Game',
            'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'tags' => $ids,
            'status' => GameStatus::Draft->value,
            'published_at' => now(),
            'releases' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $game = Game::query()->where('slug', 'imported-tags-game')->firstOrFail();

    expect($game->tags()->pluck('tags.id')->all())
        ->toEqualCanonicalizing($ids);
});

test('bulk creating tags via create option does not fail tags validation', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    $existing = Tag::factory()->create(['name' => 'Existing', 'slug' => 'existing']);

    Livewire::test(CreateGame::class)
        ->fillForm([
            'title' => 'Bulk Create Tags Game',
            'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'tags' => [$existing->id],
            'status' => GameStatus::Draft->value,
            'published_at' => now(),
            'releases' => [],
        ])
        ->callFormComponentAction('tags', 'createOption', data: [
            'names' => "Romance, Fantasy\nComedy",
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $game = Game::query()->where('slug', 'bulk-create-tags-game')->firstOrFail();

    expect($game->tags()->pluck('name')->all())
        ->toEqualCanonicalizing(['Existing', 'Romance', 'Fantasy', 'Comedy']);
});
