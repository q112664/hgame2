<?php

use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\GameScreenshot;
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
    Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);
    Platform::factory()->create([
        'name' => 'Mac',
        'slug' => 'mac',
    ]);
    Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);
    Language::factory()->create([
        'name' => 'English',
        'code' => 'en',
    ]);
    Language::factory()->create([
        'name' => 'Japanese',
        'code' => 'ja',
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Http::fake([
        'https://example.com/*' => fn () => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function publishGameViaApi(array $overrides = []): array
{
    $payload = array_replace_recursive([
        'title' => 'Senren Banka',
        'category' => 'Visual Novel',
        'tags' => ['Romance'],
        'developer' => 'Yuzu Soft',
        'cover_url' => 'https://example.com/cover.png',
        'status' => GameStatus::Published->value,
        'screenshots' => ['https://example.com/shot-1.png'],
        'detail_versions' => [[
            'language' => 'en',
            'description' => '<p>English details</p>',
        ]],
        'releases' => [[
            'title' => 'Windows Chinese package',
            'platforms' => ['Windows'],
            'languages' => ['Chinese'],
            'version' => '1.0',
            'download_links' => ['https://example.com/game.zip'],
        ]],
    ], $overrides);

    return test()->postJson('/api/v1/games', $payload)
        ->assertCreated()
        ->json('data');
}

test('nested publish endpoints require an admin token', function () {
    $this->postJson('/api/v1/games/senren-banka/screenshots', [
        'url' => 'https://example.com/shot-2.png',
    ])->assertUnauthorized();
});

test('game detail includes stable nested ids', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    expect($data['screenshot_items'])->toHaveCount(1)
        ->and($data['screenshot_items'][0]['id'])->toBeInt()
        ->and($data['releases'][0]['id'])->toBeInt()
        ->and($data['releases'][0]['download_link_items'][0]['id'])->toBeInt()
        ->and($data['detail_versions'][0]['id'])->toBeInt()
        ->and($data['screenshots'][0])->toBeString();
});

test('adding a screenshot does not replace existing screenshots or releases', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $releaseId = $data['releases'][0]['id'];
    $screenshotId = $data['screenshot_items'][0]['id'];

    $this->postJson("/api/v1/games/{$data['id']}/screenshots", [
        'url' => 'https://example.com/shot-2.png',
    ])
        ->assertCreated()
        ->assertJsonPath('data.screenshots_count', 2)
        ->assertJsonPath('data.releases_count', 1)
        ->assertJsonPath('data.releases.0.id', $releaseId)
        ->assertJsonPath('data.screenshot_items.0.id', $screenshotId)
        ->assertJsonPath('data.tags.0', 'Romance');
});

test('replacing a screenshot url keeps the screenshot id', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $screenshotId = $data['screenshot_items'][0]['id'];
    $oldUrl = $data['screenshot_items'][0]['url'];

    $this->patchJson("/api/v1/games/{$data['id']}/screenshots/{$screenshotId}", [
        'url' => 'https://example.com/shot-replaced.png',
    ])
        ->assertOk()
        ->assertJsonPath('data.screenshots_count', 1)
        ->assertJsonPath('data.screenshot_items.0.id', $screenshotId);

    expect($this->getJson("/api/v1/games/{$data['id']}")->json('data.screenshot_items.0.url'))
        ->not->toBe($oldUrl);
});

test('screenshot sort_order moves that item without dropping others', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi([
        'screenshots' => [
            'https://example.com/shot-1.png',
            'https://example.com/shot-2.png',
        ],
    ]);

    $firstId = $data['screenshot_items'][0]['id'];
    $secondId = $data['screenshot_items'][1]['id'];

    $this->patchJson("/api/v1/games/{$data['id']}/screenshots/{$firstId}", [
        'sort_order' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.screenshot_items.0.id', $secondId)
        ->assertJsonPath('data.screenshot_items.1.id', $firstId);
});

test('deleting a screenshot leaves the rest of the game intact', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi([
        'screenshots' => [
            'https://example.com/shot-1.png',
            'https://example.com/shot-2.png',
        ],
    ]);

    $keepId = $data['screenshot_items'][1]['id'];
    $deleteId = $data['screenshot_items'][0]['id'];

    $this->deleteJson("/api/v1/games/{$data['id']}/screenshots/{$deleteId}")
        ->assertOk()
        ->assertJsonPath('data.screenshots_count', 1)
        ->assertJsonPath('data.screenshot_items.0.id', $keepId)
        ->assertJsonPath('data.releases_count', 1);
});

test('nested screenshot add and delete keep url-only screenshots', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $game = Game::query()->where('slug', $data['id'])->firstOrFail();
    $pathBackedId = $data['screenshot_items'][0]['id'];

    $urlOnly = GameScreenshot::factory()->create([
        'game_id' => $game->id,
        'path' => null,
        'url' => 'https://cdn.example.com/legacy-shot.png',
        'sort_order' => 99,
    ]);

    $this->postJson("/api/v1/games/{$data['id']}/screenshots", [
        'url' => 'https://example.com/shot-2.png',
    ])
        ->assertCreated()
        ->assertJsonPath('data.screenshots_count', 3);

    $this->assertModelExists($urlOnly);
    expect(GameScreenshot::query()->whereKey($pathBackedId)->exists())->toBeTrue();

    $this->deleteJson("/api/v1/games/{$data['id']}/screenshots/{$pathBackedId}")
        ->assertOk()
        ->assertJsonPath('data.screenshots_count', 2);

    $this->assertModelExists($urlOnly);
    expect(GameScreenshot::query()->whereKey($pathBackedId)->exists())->toBeFalse();
});

test('screenshots from another game cannot be edited through this game', function () {
    Sanctum::actingAs($this->admin);

    $first = publishGameViaApi();
    $second = publishGameViaApi([
        'title' => 'Other Game',
        'slug' => 'other-game',
    ]);

    $foreignId = $second['screenshot_items'][0]['id'];

    $this->patchJson("/api/v1/games/{$first['id']}/screenshots/{$foreignId}", [
        'url' => 'https://example.com/stolen.png',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/games/{$first['id']}/screenshots/{$foreignId}")
        ->assertNotFound();
});

test('a game cannot exceed the screenshot limit through nested create', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $game = Game::query()->where('slug', $data['id'])->firstOrFail();

    GameScreenshot::factory()
        ->count(GameScreenshot::MaxPerGame - 1)
        ->create(['game_id' => $game->id]);

    $this->postJson("/api/v1/games/{$data['id']}/screenshots", [
        'url' => 'https://example.com/shot-overflow.png',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

test('attaching tags keeps existing tags', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->postJson("/api/v1/games/{$data['id']}/tags", [
        'tags' => ['Drama', 'Slice of Life'],
    ])
        ->assertCreated();

    expect($this->getJson("/api/v1/games/{$data['id']}")->json('data.tags'))
        ->toEqualCanonicalizing(['Romance', 'Drama', 'Slice of Life']);
});

test('tags can be detached by slug or name', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->postJson("/api/v1/games/{$data['id']}/tags", [
        'tags' => ['Slice of Life'],
    ])->assertCreated();

    $this->deleteJson("/api/v1/games/{$data['id']}/tags/romance")
        ->assertOk();

    expect($this->getJson("/api/v1/games/{$data['id']}")->json('data.tags'))
        ->toEqualCanonicalizing(['Slice of Life']);

    $this->deleteJson('/api/v1/games/'.$data['id'].'/tags/'.rawurlencode('Slice of Life'))
        ->assertOk();

    expect($this->getJson("/api/v1/games/{$data['id']}")->json('data.tags'))
        ->toBeEmpty();
});

test('detaching an unknown tag returns 404', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->deleteJson("/api/v1/games/{$data['id']}/tags/missing-tag")
        ->assertNotFound();
});

test('upserting one localized detail version leaves the others', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->putJson("/api/v1/games/{$data['id']}/detail-versions/ja", [
        'description' => '<p>日本語</p>',
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.detail_versions');

    $versions = $this->getJson("/api/v1/games/{$data['id']}")->json('data.detail_versions');
    $codes = collect($versions)->pluck('language.code')->all();

    expect($codes)->toEqualCanonicalizing(['en', 'ja'])
        ->and(collect($versions)->firstWhere('language.code', 'en')['description'])->toBe('<p>English details</p>')
        ->and(collect($versions)->firstWhere('language.code', 'ja')['description'])->toBe('<p>日本語</p>');
});

test('a localized detail version can be deleted by language', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->putJson("/api/v1/games/{$data['id']}/detail-versions/ja", [
        'description' => '<p>日本語</p>',
    ])->assertOk();

    $this->deleteJson("/api/v1/games/{$data['id']}/detail-versions/en")
        ->assertOk()
        ->assertJsonCount(1, 'data.detail_versions')
        ->assertJsonPath('data.detail_versions.0.language.code', 'ja');
});

test('nested detail versions accept PATCH and case-insensitive language codes', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->patchJson("/api/v1/games/{$data['id']}/detail-versions/JA", [
        'description' => '<p>日本語</p>',
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.detail_versions');

    $this->patchJson("/api/v1/games/{$data['id']}/detail-versions/EN", [
        'description' => '<p>Updated English</p>',
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.detail_versions');

    $versions = $this->getJson("/api/v1/games/{$data['id']}")->json('data.detail_versions');

    expect(collect($versions)->firstWhere('language.code', 'en')['description'])->toBe('<p>Updated English</p>')
        ->and(collect($versions)->firstWhere('language.code', 'ja')['description'])->toBe('<p>日本語</p>');
});

test('unknown languages are rejected for nested detail versions', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->putJson("/api/v1/games/{$data['id']}/detail-versions/klingon", [
        'description' => '<p>Nope</p>',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language']);
});

test('adding a release keeps existing packages', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $existingId = $data['releases'][0]['id'];

    $this->postJson("/api/v1/games/{$data['id']}/releases", [
        'title' => 'Mac English package',
        'platforms' => ['Mac'],
        'languages' => ['English'],
        'version' => '1.0',
        'download_links' => ['https://example.com/mac.zip'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.releases_count', 2);

    $ids = collect($this->getJson("/api/v1/games/{$data['id']}")->json('data.releases'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($existingId);
});

test('patching a release updates in place and keeps unpublished fields', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $releaseId = $data['releases'][0]['id'];
    $game = Game::query()->where('slug', $data['id'])->firstOrFail();
    $release = $game->releases()->firstOrFail();
    $originalPublishedAt = $release->published_at?->copy();

    $release->forceFill([
        'published_at' => '2020-01-02 03:04:05',
        'version' => '1.0',
    ])->save();

    $this->patchJson("/api/v1/games/{$data['id']}/releases/{$releaseId}", [
        'version' => '1.1',
        'download_links' => ['https://example.com/game-v1-1.zip'],
    ])
        ->assertOk()
        ->assertJsonPath('data.releases_count', 1)
        ->assertJsonPath('data.releases.0.id', $releaseId)
        ->assertJsonPath('data.releases.0.version', '1.1')
        ->assertJsonPath('data.releases.0.download_links.0', 'https://example.com/game-v1-1.zip')
        ->assertJsonPath('data.releases.0.title', 'Windows Chinese package');

    $updated = $game->releases()->firstOrFail();

    expect($updated->id)->toBe($releaseId)
        ->and($updated->published_at?->toDateTimeString())->toBe('2020-01-02 03:04:05')
        ->and($game->fresh()->downloads_updated_at)->toBeNull()
        ->and($originalPublishedAt)->not->toBeNull();
});

test('nested release updates bump downloads only when touch_downloads is true', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();
    $releaseId = $data['releases'][0]['id'];

    $this->patchJson("/api/v1/games/{$data['id']}/releases/{$releaseId}", [
        'version' => '1.1',
        'touch_downloads' => false,
    ])->assertOk();

    $game = Game::query()->where('slug', $data['id'])->firstOrFail();
    expect($game->downloads_updated_at)->toBeNull();

    $this->patchJson("/api/v1/games/{$data['id']}/releases/{$releaseId}", [
        'touch_downloads' => true,
    ])->assertOk();

    expect($game->fresh()->downloads_updated_at)->not->toBeNull();
});

test('deleting a release leaves other packages', function () {
    Sanctum::actingAs($this->admin);

    $data = publishGameViaApi();

    $this->postJson("/api/v1/games/{$data['id']}/releases", [
        'title' => 'Mac English package',
        'platforms' => ['Mac'],
        'languages' => ['English'],
        'download_links' => ['https://example.com/mac.zip'],
    ])->assertCreated();

    $ids = collect($this->getJson("/api/v1/games/{$data['id']}")->json('data.releases'))
        ->pluck('id')
        ->all();

    expect($ids)->toHaveCount(2);

    $this->deleteJson("/api/v1/games/{$data['id']}/releases/{$ids[0]}")
        ->assertOk()
        ->assertJsonPath('data.releases_count', 1)
        ->assertJsonPath('data.releases.0.id', $ids[1]);
});

test('releases from another game cannot be edited through this game', function () {
    Sanctum::actingAs($this->admin);

    $first = publishGameViaApi();
    $second = publishGameViaApi([
        'title' => 'Other Game',
        'slug' => 'other-game-releases',
    ]);

    $foreignId = $second['releases'][0]['id'];

    $this->patchJson("/api/v1/games/{$first['id']}/releases/{$foreignId}", [
        'version' => '9.9',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/games/{$first['id']}/releases/{$foreignId}")
        ->assertNotFound();
});
