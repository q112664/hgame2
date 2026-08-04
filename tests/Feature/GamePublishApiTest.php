<?php

use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
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

function validGamePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'title' => 'Senren Banka',
        'subtitle' => 'A spring tale',
        'category' => 'Visual Novel',
        'tags' => ['Romance', 'Slice of Life'],
        'developer' => 'Yuzu Soft',
        'source_name' => 'DLsite',
        'source_id' => 'RJ01123456',
        'source_url' => 'https://www.dlsite.com/maniax/work/=/product_id/RJ01123456.html',
        'release_date' => '2016-07-29',
        'description' => '<p>A published visual novel.</p>',
        'cover_url' => 'https://example.com/cover.png',
        'status' => GameStatus::Published->value,
        'screenshots' => [
            'https://example.com/shot-1.png',
            'https://example.com/shot-2.png',
        ],
        'releases' => [[
            'title' => 'Windows Chinese package',
            'platforms' => ['Windows'],
            'languages' => ['Chinese'],
            'version' => '1.0',
            'file_size' => '5.4 GB',
            'download_links' => [
                'https://example.com/game.zip',
            ],
        ]],
    ], $overrides);
}

test('unauthenticated users cannot publish games', function () {
    $this->postJson('/api/v1/games', validGamePayload())
        ->assertUnauthorized();
});

test('non-admin users cannot publish games', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/games', validGamePayload())
        ->assertForbidden();
});

test('an administrator can publish a complete game via the api', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/games', validGamePayload())
        ->assertCreated()
        ->assertJsonPath('data.id', 'senren-banka')
        ->assertJsonPath('data.title', 'Senren Banka')
        ->assertJsonPath('data.status', GameStatus::Published->value)
        ->assertJsonPath('data.screenshots_count', 2)
        ->assertJsonPath('data.releases_count', 1);

    expect($response->json('data.url'))->toBe(route('resources.details', 'senren-banka'));

    $game = Game::query()->where('slug', 'senren-banka')->firstOrFail();

    expect($game->category?->name)->toBe('Visual Novel')
        ->and($game->developer)->toBe('Yuzu Soft')
        ->and($game->source_name)->toBe('DLsite')
        ->and($game->source_id)->toBe('RJ01123456')
        ->and($game->source_url)->toContain('RJ01123456')
        ->and($game->tags()->pluck('name')->all())->toEqualCanonicalizing(['Romance', 'Slice of Life'])
        ->and($game->screenshots)->toHaveCount(2)
        ->and($game->releases)->toHaveCount(1)
        ->and($game->releases->first()->platforms->pluck('name')->all())->toBe(['Windows'])
        ->and($game->releases->first()->languages->pluck('name')->all())->toBe(['Chinese'])
        ->and($game->releases->first()->downloadLinks)->toHaveCount(1)
        ->and($game->downloads_updated_at)->toBeNull();

    Storage::disk(Media::diskName())->assertExists($game->cover_path);
    Storage::disk(Media::diskName())->assertExists($game->screenshots->first()->path);

    $this->get(route('resources.details', $game->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.source.name', 'DLsite')
            ->where('resource.source.id', 'RJ01123456')
            ->where('resource.source.faviconUrl', '/images/sources/dlsite.ico')
        );
});

test('publishing rejects unknown categories and platforms', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/games', validGamePayload([
        'category' => 'Missing Category',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['category']);

    $this->postJson('/api/v1/games', validGamePayload([
        'category' => 'Visual Novel',
        'releases' => [[
            'title' => 'Bad platform package',
            'platforms' => ['Sega Saturn'],
            'languages' => ['Chinese'],
            'download_links' => ['https://example.com/game.zip'],
        ]],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['releases']);
});

test('publishing rejects duplicate slugs', function () {
    Sanctum::actingAs($this->admin);

    Game::factory()->create([
        'slug' => 'senren-banka',
        'category_id' => $this->category->id,
    ]);

    $this->postJson('/api/v1/games', validGamePayload([
        'slug' => 'senren-banka',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('administrators can list taxonomies and inspect a published game', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/taxonomies')
        ->assertOk()
        ->assertJsonPath('data.categories.0.name', 'Visual Novel')
        ->assertJsonPath('data.platforms.0.slug', 'windows')
        ->assertJsonPath('data.languages.0.code', 'zh')
        ->assertJsonPath('data.sources.0.name', 'DLsite')
        ->assertJsonPath('data.sources.0.favicon_url', '/images/sources/dlsite.ico')
        ->assertJsonPath('data.sources.1.name', 'Steam')
        ->assertJsonPath('data.sources.1.favicon_url', '/images/sources/steam.ico');

    $this->postJson('/api/v1/games', validGamePayload())->assertCreated();

    $this->getJson('/api/v1/games/senren-banka')
        ->assertOk()
        ->assertJsonPath('data.id', 'senren-banka')
        ->assertJsonPath('data.screenshots_count', 2)
        ->assertJsonPath('data.source.faviconUrl', '/images/sources/dlsite.ico');
});

test('publishing a steam-sourced game uses the local steam favicon', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/games', validGamePayload([
        'title' => 'Steam Demo Game',
        'slug' => 'steam-demo-game',
        'source_name' => 'Steam',
        'source_id' => '1234560',
        'source_url' => 'https://store.steampowered.com/app/1234560/Steam_Demo_Game/',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.source.name', 'Steam')
        ->assertJsonPath('data.source.id', '1234560')
        ->assertJsonPath('data.source.faviconUrl', '/images/sources/steam.ico');

    $this->get(route('resources.details', 'steam-demo-game'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('resource.source.name', 'Steam')
            ->where('resource.source.id', '1234560')
            ->where('resource.source.faviconUrl', '/images/sources/steam.ico')
        );
});

test('publishing downloads remote images embedded in description html', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/games', validGamePayload([
        'description' => '<p>Intro</p><p><img src="https://example.com/detail-1.png" alt="Scene"></p><p><img src="https://example.com/detail-1.png"></p>',
        'releases' => [[
            'title' => 'Windows Chinese package',
            'platforms' => ['Windows'],
            'languages' => ['Chinese'],
            'description' => '<p>Notes <img src="https://example.com/release-note.png"></p>',
            'download_links' => [
                'https://example.com/game.zip',
            ],
        ]],
    ]))->assertCreated();

    $game = Game::query()->where('slug', 'senren-banka')->firstOrFail();

    expect($game->description)
        ->toMatch('#src="/storage/games/content/[^"]+\.png"#')
        ->not->toContain('https://example.com/detail-1.png')
        ->and(substr_count((string) $game->description, '/storage/games/content/'))->toBe(2)
        ->and($game->releases->first()->description)
        ->toMatch('#src="/storage/games/content/[^"]+\.png"#')
        ->not->toContain('https://example.com/release-note.png');

    preg_match_all('#/storage/(games/content/[^"]+)#', (string) $game->description, $matches);
    foreach ($matches[1] as $path) {
        Storage::disk(Media::diskName())->assertExists($path);
    }

    preg_match('#/storage/(games/content/[^"]+)#', (string) $game->releases->first()->description, $releaseMatch);
    Storage::disk(Media::diskName())->assertExists($releaseMatch[1]);
});

test('publishing leaves already-local description images unchanged', function () {
    Sanctum::actingAs($this->admin);

    $local = '/storage/games/content/already-there.png';

    $this->postJson('/api/v1/games', validGamePayload([
        'description' => '<p><img src="'.$local.'"></p>',
    ]))->assertCreated();

    $game = Game::query()->where('slug', 'senren-banka')->firstOrFail();

    expect($game->description)->toContain('src="'.$local.'"')
        ->and(Storage::disk(Media::diskName())->allFiles('games/content'))->toBeEmpty();
});

test('publishing rejects data uri images in description', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/games', validGamePayload([
        'description' => '<p><img src="data:image/png;base64,aaaa"></p>',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

test('publishing rejects unreachable description images and cleans up prior media', function () {
    Sanctum::actingAs($this->admin);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    // Replace the beforeEach stubs (Http::fake merges; earlier example.com/* would always win).
    Http::swap(new Factory);
    Http::fake(function (Request $request) use ($png) {
        if (str_contains($request->url(), 'broken-detail')) {
            return Http::response('nope', 404);
        }

        return Http::response($png, 200, ['Content-Type' => 'image/png']);
    });

    $this->postJson('/api/v1/games', validGamePayload([
        'description' => '<p><img src="https://example.com/broken-detail.png"></p>',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);

    expect(Storage::disk(Media::diskName())->allFiles('games/covers'))->toBeEmpty()
        ->and(Storage::disk(Media::diskName())->allFiles('games/screenshots'))->toBeEmpty()
        ->and(Storage::disk(Media::diskName())->allFiles('games/content'))->toBeEmpty()
        ->and(Game::query()->where('slug', 'senren-banka')->exists())->toBeFalse();
});
