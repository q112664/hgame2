<?php

use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(Media::diskName());
    Storage::fake('s3');

    $this->admin = User::factory()->admin()->create();
    $this->category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $this->platform = Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);
    $this->language = Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Http::fake([
        'https://example.com/*' => fn () => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);
});

function createGameViaApi(array $overrides = []): string
{
    $payload = array_replace_recursive([
        'title' => 'Senren Banka',
        'category' => 'Visual Novel',
        'tags' => ['Romance'],
        'developer' => 'Yuzu Soft',
        'cover_url' => 'https://example.com/cover.png',
        'status' => GameStatus::Published->value,
        'screenshots' => ['https://example.com/shot-1.png'],
        'releases' => [[
            'title' => 'Windows Chinese package',
            'platforms' => ['Windows'],
            'languages' => ['Chinese'],
            'version' => '1.0',
            'download_links' => ['https://example.com/game.zip'],
        ]],
    ], $overrides);

    $response = test()->postJson('/api/v1/games', $payload)->assertCreated();

    return (string) $response->json('data.id');
}

test('administrators can list games with filters', function () {
    Sanctum::actingAs($this->admin);

    createGameViaApi();
    createGameViaApi([
        'title' => 'Draft Title',
        'slug' => 'draft-title',
        'status' => GameStatus::Draft->value,
    ]);
    Game::factory()->create([
        'title' => 'Other Category Game',
        'slug' => 'other-category',
        'category_id' => Category::factory()->create([
            'name' => 'Action',
            'slug' => 'action',
        ])->id,
        'status' => GameStatus::Published,
    ]);

    $this->getJson('/api/v1/games')
        ->assertOk()
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/v1/games?status=draft')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', 'draft-title');

    $this->getJson('/api/v1/games?category=visual-novel')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/games?q=Senren')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Senren Banka');
});

test('unauthenticated users cannot list games', function () {
    $this->getJson('/api/v1/games')->assertUnauthorized();
});

test('administrators can inspect full game details', function () {
    Sanctum::actingAs($this->admin);

    $slug = createGameViaApi();

    $this->getJson("/api/v1/games/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $slug)
        ->assertJsonPath('data.developer', 'Yuzu Soft')
        ->assertJsonPath('data.tags.0', 'Romance')
        ->assertJsonPath('data.releases.0.platforms.0', 'Windows')
        ->assertJsonPath('data.releases.0.download_links.0', 'https://example.com/game.zip')
        ->assertJsonPath('data.screenshots_count', 1)
        ->assertJsonPath('data.releases_count', 1);

    expect(test()->getJson("/api/v1/games/{$slug}")->json('data.cover_url'))->not->toBeEmpty()
        ->and(test()->getJson("/api/v1/games/{$slug}")->json('data.screenshots'))->toHaveCount(1);
});

test('administrators can create and replace localized details through the api', function () {
    Sanctum::actingAs($this->admin);
    $english = Language::factory()->create(['name' => 'English', 'code' => 'en']);
    $japanese = Language::factory()->create(['name' => 'Japanese', 'code' => 'ja']);

    $slug = createGameViaApi([
        'description' => '<p>Original details</p>',
        'detail_versions' => [
            [
                'language' => 'Japanese',
                'description' => '<p>Japanese details</p>',
                'sort_order' => 20,
            ],
            [
                'language' => 'en',
                'description' => '<p>English details</p>',
                'sort_order' => 10,
            ],
        ],
    ]);

    $this->getJson("/api/v1/games/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.description', '<p>Original details</p>')
        ->assertJsonPath('data.detail_versions.0.language.code', 'en')
        ->assertJsonPath('data.detail_versions.0.description', '<p>English details</p>')
        ->assertJsonPath('data.detail_versions.1.language.code', 'ja');

    $this->patchJson("/api/v1/games/{$slug}", [
        'detail_versions' => [[
            'language' => 'ja',
            'description' => '<p>Updated Japanese details</p>',
        ]],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.detail_versions')
        ->assertJsonPath('data.description', '<p>Original details</p>')
        ->assertJsonPath('data.detail_versions.0.language.code', 'ja')
        ->assertJsonPath('data.detail_versions.0.description', '<p>Updated Japanese details</p>');

    $game = Game::query()->where('slug', $slug)->firstOrFail();

    expect($game->detailTranslations)->toHaveCount(1)
        ->and($game->detailTranslations->first()->language_id)->toBe($japanese->id)
        ->and($game->detailTranslations()->where('language_id', $english->id)->exists())->toBeFalse();
});

test('api rejects duplicate localized detail languages', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/games', [
        'title' => 'Duplicate Detail Languages',
        'cover_url' => 'https://example.com/cover.png',
        'detail_versions' => [
            ['language' => 'zh', 'description' => '<p>Chinese by code</p>'],
            ['language' => 'Chinese', 'description' => '<p>Chinese by name</p>'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['detail_versions']);

    expect(Game::query()->where('title', 'Duplicate Detail Languages')->exists())->toBeFalse();
});

test('administrators can partially update a game', function () {
    Sanctum::actingAs($this->admin);

    $slug = createGameViaApi();

    $this->patchJson("/api/v1/games/{$slug}", [
        'title' => 'Senren Banka Revised',
        'status' => GameStatus::Unlisted->value,
        'tags' => ['Romance', 'Drama'],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Senren Banka Revised')
        ->assertJsonPath('data.status', GameStatus::Unlisted->value)
        ->assertJsonPath('data.screenshots_count', 1)
        ->assertJsonPath('data.releases_count', 1);

    $game = Game::query()->where('slug', $slug)->firstOrFail();

    expect($game->title)->toBe('Senren Banka Revised')
        ->and($game->status)->toBe(GameStatus::Unlisted)
        ->and($game->developer)->toBe('Yuzu Soft')
        ->and($game->tags()->pluck('name')->all())->toEqualCanonicalizing(['Romance', 'Drama'])
        ->and($game->screenshots)->toHaveCount(1)
        ->and($game->releases)->toHaveCount(1);
});

test('updating releases replaces the previous set', function () {
    Sanctum::actingAs($this->admin);

    $slug = createGameViaApi();

    $this->patchJson("/api/v1/games/{$slug}", [
        'releases' => [[
            'title' => 'Mac Chinese package',
            'platforms' => ['Windows'],
            'languages' => ['Chinese'],
            'version' => '2.0',
            'download_links' => ['https://example.com/game-v2.zip'],
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('data.releases_count', 1)
        ->assertJsonPath('data.releases.0.title', 'Mac Chinese package')
        ->assertJsonPath('data.releases.0.version', '2.0');

    $game = Game::query()->where('slug', $slug)->firstOrFail();

    expect($game->releases)->toHaveCount(1)
        ->and($game->releases->first()->title)->toBe('Mac Chinese package')
        ->and($game->releases->first()->downloadLinks)->toHaveCount(1)
        ->and($game->releases->first()->downloadLinks->first()->url)->toBe('https://example.com/game-v2.zip');
});

test('updating screenshots replaces previous screenshots', function () {
    Sanctum::actingAs($this->admin);

    $slug = createGameViaApi([
        'screenshots' => [
            'https://example.com/shot-1.png',
            'https://example.com/shot-2.png',
        ],
    ]);

    $game = Game::query()->where('slug', $slug)->firstOrFail();
    $oldPath = $game->screenshots->first()->path;

    $this->patchJson("/api/v1/games/{$slug}", [
        'screenshots' => ['https://example.com/shot-3.png'],
    ])
        ->assertOk()
        ->assertJsonPath('data.screenshots_count', 1);

    $game->refresh();

    expect($game->screenshots)->toHaveCount(1)
        ->and($game->screenshots->first()->path)->not->toBe($oldPath);

    Storage::disk(Media::diskName())->assertExists($game->screenshots->first()->path);
});

test('administrators can delete a game and its media', function () {
    Sanctum::actingAs($this->admin);

    $slug = createGameViaApi();
    $game = Game::query()->where('slug', $slug)->firstOrFail();
    $coverPath = $game->cover_path;

    Storage::disk(Media::diskName())->assertExists($coverPath);

    $this->deleteJson("/api/v1/games/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $slug)
        ->assertJsonPath('data.deleted', true);

    expect(Game::query()->where('slug', $slug)->exists())->toBeFalse();

    Storage::disk(Media::diskName())->assertMissing($coverPath);

    $this->getJson("/api/v1/games/{$slug}")->assertNotFound();
});

test('non-admin users cannot update or delete games', function () {
    Sanctum::actingAs($this->admin);
    $slug = createGameViaApi();

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson("/api/v1/games/{$slug}", ['title' => 'Nope'])
        ->assertForbidden();

    $this->deleteJson("/api/v1/games/{$slug}")
        ->assertForbidden();

    expect(Game::query()->where('slug', $slug)->exists())->toBeTrue();
});

test('updating rejects duplicate slugs', function () {
    Sanctum::actingAs($this->admin);

    createGameViaApi(['slug' => 'first-game', 'title' => 'First']);
    $slug = createGameViaApi(['slug' => 'second-game', 'title' => 'Second']);

    $this->patchJson("/api/v1/games/{$slug}", [
        'slug' => 'first-game',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});
