<?php

use App\Filament\Resources\Games\Pages\CreateGame;
use App\GameStatus;
use App\Models\Game;
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
            'slug' => 'example-game',
            'cover_path' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'cover_url' => '',
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
                'file_size_bytes' => 5_800_000_000,
                'description' => '<p>Release notes</p>',
                'is_active' => true,
                'published_at' => now(),
                'downloadLinks' => [[
                    'label' => 'Direct Download',
                    'url' => 'https://example.com/game.zip',
                    'is_active' => true,
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
        ->and($game->releases->first()->platforms)->toHaveCount(1)
        ->and($game->releases->first()->languages)->toHaveCount(1)
        ->and($game->releases->first()->downloadLinks)->toHaveCount(1);

    Storage::disk('public')->assertExists($game->cover_path);
    Storage::disk('public')->assertExists($game->screenshots->first()->path);
});
