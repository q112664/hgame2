<?php

use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\ReleasesRelationManager;
use App\GameStatus;
use App\Models\Game;
use App\Models\GameScreenshot;
use App\Models\Language;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an administrator can create a game with a release and download link', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());
    $platform = Platform::factory()->create();
    $language = Language::factory()->create();

    Livewire::test(CreateGame::class)
        ->fillForm([
            'title' => 'Example Game',
            'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'description' => '<p><strong>Rich details</strong></p>',
            'screenshot_uploads' => [
                UploadedFile::fake()->image('screenshot-one.jpg', 1280, 720),
                UploadedFile::fake()->image('screenshot-two.jpg', 1280, 720),
            ],
            'status' => GameStatus::Published->value,
            'published_at' => now(),
            'releases' => [[
                'platforms' => [$platform->id],
                'languages' => [$language->id],
                'title' => 'Windows Chinese package',
                'file_size' => '5.4 GB',
                'description' => '<p>Release notes</p>',
                'is_active' => true,
                'published_at' => now(),
                'downloadLinks' => [[
                    'url' => 'https://example.com/game.zip',
                ]],
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $game = Game::query()->where('slug', 'example-game')->firstOrFail();

    expect($game->cover_path)->not->toBeNull()
        ->and($game->screenshots)->toHaveCount(2)
        ->and($game->screenshots->first()->path)->not->toBeNull()
        ->and($game->releases)->toHaveCount(1)
        ->and($game->releases->first()->title)->toBe('Windows Chinese package')
        ->and($game->releases->first()->platforms)->toHaveCount(1)
        ->and($game->releases->first()->languages)->toHaveCount(1)
        ->and($game->releases->first()->downloadLinks)->toHaveCount(1)
        ->and($game->releases->first()->downloadLinks->first()->url)->toBe('https://example.com/game.zip')
        ->and($game->releases->first()->downloadLinks->first()->is_active)->toBeTrue();

    Storage::disk('public')->assertExists($game->cover_path);
    Storage::disk('public')->assertExists($game->screenshots->first()->path);
});

test('an administrator can create a game with multiple screenshots at once', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateGame::class)
        ->fillForm([
            'title' => 'Multi Screenshot Game',
            'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'screenshot_uploads' => [
                UploadedFile::fake()->image('shot-1.jpg', 1280, 720),
                UploadedFile::fake()->image('shot-2.jpg', 1280, 720),
                UploadedFile::fake()->image('shot-3.jpg', 1280, 720),
            ],
            'status' => GameStatus::Draft->value,
            'published_at' => now(),
            'releases' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $game = Game::query()->where('slug', 'multi-screenshot-game')->firstOrFail();

    expect($game->screenshots)->toHaveCount(3)
        ->and($game->screenshots->pluck('sort_order')->all())->toBe([0, 1, 2]);

    foreach ($game->screenshots as $screenshot) {
        Storage::disk('public')->assertExists($screenshot->path);
    }
});

test('edit game page uses the create-style screenshots upload and only stacks releases', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());

    $game = Game::factory()->create([
        'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720)->store('games/covers', 'public'),
    ]);
    $kept = GameScreenshot::factory()->for($game)->create([
        'path' => UploadedFile::fake()->image('kept.jpg', 1280, 720)->store('games/screenshots', 'public'),
        'sort_order' => 0,
    ]);
    $removed = GameScreenshot::factory()->for($game)->create([
        'path' => UploadedFile::fake()->image('removed.jpg', 1280, 720)->store('games/screenshots', 'public'),
        'sort_order' => 1,
    ]);

    $component = Livewire::test(EditGame::class, [
        'record' => $game->getRouteKey(),
    ]);

    $component
        ->assertSuccessful()
        ->assertFormFieldExists('screenshot_uploads')
        ->assertSeeLivewire(ReleasesRelationManager::class)
        ->assertFormSet([
            'screenshot_uploads' => [$kept->path, $removed->path],
        ]);

    expect($component->instance()->getRelationManagers())->toHaveCount(1)
        ->and($component->html())->not->toContain('wire:key="relationManagerTabs"');

    $component
        ->fillForm([
            'screenshot_uploads' => [
                $kept->path,
                UploadedFile::fake()->image('added.jpg', 1280, 720),
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $game->refresh();

    expect($game->screenshots)->toHaveCount(2)
        ->and($game->screenshots->pluck('path')->contains($kept->path))->toBeTrue()
        ->and($game->screenshots()->where('path', $removed->path)->exists())->toBeFalse()
        ->and($game->screenshots->pluck('sort_order')->all())->toBe([0, 1]);

    Storage::disk('public')->assertExists($kept->path);
    Storage::disk('public')->assertMissing($removed->path);
});

test('games list defaults to newest created first', function () {
    $this->actingAs(User::factory()->admin()->create());

    $older = Game::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = Game::factory()->create(['created_at' => now()->subDay()]);
    $newest = Game::factory()->create(['created_at' => now()]);

    Livewire::test(ListGames::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$newest, $newer, $older], inOrder: true);
});
