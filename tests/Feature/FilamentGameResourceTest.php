<?php

use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\ReleasesRelationManager;
use App\Filament\Resources\Games\RelationManagers\ScreenshotsRelationManager;
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

test('edit game page stacks releases and screenshots without relation tabs', function () {
    $this->actingAs(User::factory()->admin()->create());

    $game = Game::factory()->create();
    GameScreenshot::factory()->count(2)->for($game)->create();

    $component = Livewire::test(EditGame::class, [
        'record' => $game->getRouteKey(),
    ]);

    $component
        ->assertSuccessful()
        ->assertSeeLivewire(ReleasesRelationManager::class)
        ->assertSeeLivewire(ScreenshotsRelationManager::class);

    expect($component->instance()->getRelationManagers())->toHaveCount(1)
        ->and($component->html())->not->toContain('wire:key="relationManagerTabs"');
});
