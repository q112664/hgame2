<?php

use App\GameStatus;
use App\Models\Category;
use App\Models\Language;
use App\Models\Platform;
use App\Models\ResourceSource;
use App\Models\User;
use App\Support\GameSource;
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
    Language::factory()->create([
        'name' => 'Japanese',
        'code' => 'ja',
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

    Http::fake(fn () => Http::response($png, 200, [
        'Content-Type' => 'image/png',
    ]));
});

test('api lists reusable sources from the library', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson(route('api.v1.sources.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'DLsite')
        ->assertJsonPath('data.0.favicon_url', '/images/sources/dlsite.ico')
        ->assertJsonPath('data.1.name', 'Steam');
});

test('api can register a reusable source with an icon url', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(route('api.v1.sources.store'), [
        'name' => 'Booth',
        'slug' => 'booth',
        'host_hint' => 'booth.pm',
        'icon_url' => 'https://example.com/booth.png',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Booth')
        ->assertJsonPath('data.slug', 'booth')
        ->assertJsonPath('data.host_hint', 'booth.pm');

    $source = ResourceSource::query()->where('slug', 'booth')->first();

    expect($source)->not->toBeNull()
        ->and($source->icon_path)->toStartWith('site/sources/')
        ->and(GameSource::present('Booth', null, null)['faviconUrl'])->toContain('/storage/');
});

test('api can delete a reusable source', function () {
    Sanctum::actingAs($this->admin);

    $source = ResourceSource::factory()->create([
        'name' => 'Temp Shop',
        'slug' => 'temp-shop',
    ]);

    $this->deleteJson(route('api.v1.sources.destroy', $source))
        ->assertOk()
        ->assertJsonPath('data.id', 'temp-shop')
        ->assertJsonPath('data.deleted', true);

    expect(ResourceSource::query()->where('slug', 'temp-shop')->exists())->toBeFalse()
        ->and(GameSource::options())->not->toHaveKey('Temp Shop');
});

test('publishing a game can register a source icon for reuse', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(route('api.v1.games.store'), [
        'title' => 'Source Icon Game',
        'slug' => 'source-icon-game',
        'cover_url' => 'https://example.com/cover.png',
        'source_name' => 'FANZA',
        'source_id' => 'd_0001',
        'source_url' => 'https://www.dmm.co.jp/dc/doujin/-/detail/=/cid=d_0001/',
        'source_icon_url' => 'https://example.com/fanza.png',
        'source_host_hint' => 'dmm.co.jp',
        'status' => GameStatus::Published->value,
        'releases' => [[
            'title' => 'Main',
            'platforms' => ['Windows'],
            'languages' => ['Japanese'],
            'download_links' => ['https://example.com/file.zip'],
        ]],
    ])->assertCreated();

    $source = ResourceSource::query()->where('name', 'FANZA')->first();

    expect($source)->not->toBeNull()
        ->and($source->host_hint)->toBe('dmm.co.jp')
        ->and($source->icon_path)->toStartWith('site/sources/')
        ->and(GameSource::present('FANZA', 'd_0001', null)['faviconUrl'])->toContain('/storage/');
});
