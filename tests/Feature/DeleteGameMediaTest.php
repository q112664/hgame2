<?php

use App\Actions\Games\PublishGame;
use App\GameStatus;
use App\Models\Game;
use App\Models\GameScreenshot;
use App\Models\Platform;
use App\Support\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
});

test('deleting a game removes cover screenshots and content attachments from media disks', function () {
    $cover = 'games/covers/cover.jpg';
    $screenshot = 'games/screenshots/shot.jpg';
    $content = 'games/content/embed.jpg';

    Storage::disk('public')->put($cover, 'cover');
    Storage::disk('s3')->put($cover, 'cover');
    Storage::disk('public')->put($screenshot, 'shot');
    Storage::disk('public')->put($content, 'content');

    $game = Game::factory()->create([
        'cover_path' => $cover,
        'description' => '<p><img src="/storage/games/content/embed.jpg"></p>',
    ]);

    GameScreenshot::factory()->for($game)->create([
        'path' => $screenshot,
    ]);

    $game->delete();

    expect(Storage::disk('public')->exists($cover))->toBeFalse()
        ->and(Storage::disk('s3')->exists($cover))->toBeFalse()
        ->and(Storage::disk('public')->exists($screenshot))->toBeFalse()
        ->and(Storage::disk('public')->exists($content))->toBeFalse();
});

test('rolling back a game deletion keeps its media files', function () {
    $cover = 'games/covers/rollback.jpg';

    Storage::disk('public')->put($cover, 'cover');

    $game = Game::factory()->create(['cover_path' => $cover]);

    expect(fn () => DB::transaction(function () use ($game): void {
        $game->delete();

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);

    expect(Game::query()->whereKey($game->id)->exists())->toBeTrue()
        ->and(Storage::disk('public')->exists($cover))->toBeTrue();
});

test('failed game publish cleans up uploaded media objects', function () {
    config(['filesystems.media' => 'public']);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Http::fake([
        'https://example.com/cover.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://example.com/shot.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);

    try {
        app(PublishGame::class)->handle([
            'title' => 'Broken Publish',
            'cover_url' => 'https://example.com/cover.png',
            'status' => GameStatus::Published->value,
            'screenshots' => [
                'https://example.com/shot.png',
            ],
            'releases' => [[
                'title' => 'Bad package',
                'platforms' => ['Missing Platform'],
                'languages' => ['zh'],
                'download_links' => ['https://example.com/game.zip'],
            ]],
        ]);
    } catch (ValidationException) {
        // Expected once screenshots are uploaded and platform resolution fails.
    }

    expect(Storage::disk('public')->allFiles('games/covers'))->toBeEmpty()
        ->and(Storage::disk('public')->allFiles('games/screenshots'))->toBeEmpty()
        ->and(Game::query()->where('title', 'Broken Publish')->exists())->toBeFalse();
});

test('deleting a game keeps media still referenced by another game', function () {
    $shared = 'games/content/shared.jpg';

    Storage::disk('public')->put($shared, 'shared');

    $game = Game::factory()->create([
        'description' => '<p><img src="/storage/games/content/shared.jpg"></p>',
    ]);

    Game::factory()->create([
        'description' => '<p><img src="/storage/games/content/shared.jpg"></p>',
    ]);

    $game->delete();

    expect(Storage::disk('public')->exists($shared))->toBeTrue();
});

test('media delete removes objects from both public and s3 disks', function () {
    $path = 'avatars/user.jpg';

    Storage::disk('public')->put($path, 'avatar');
    Storage::disk('s3')->put($path, 'avatar');

    expect(Media::delete($path))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Storage::disk('s3')->exists($path))->toBeFalse();
});
