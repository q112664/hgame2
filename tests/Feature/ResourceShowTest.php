<?php

use App\Actions\Games\ListRecentResourceUpdates;
use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\GameScreenshot;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->game = Game::factory()->create([
        'slug' => 'senren-banka',
        'title' => 'Senren Banka',
        'subtitle' => 'A warm countryside love story',
        'description' => '<p><strong>Rich details</strong><script>alert(1)</script></p>',
    ]);
    $platform = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $language = Language::factory()->create(['name' => 'Chinese', 'code' => 'zh']);
    $release = GameRelease::factory()->for($this->game)->create([
        'platform_id' => $platform->id,
        'language_id' => $language->id,
        'title' => 'Official release',
        'version' => '1.2 demo',
        'file_size' => '5.4 GB',
        'description' => '<p>Release notes<script>alert(1)</script></p>',
    ]);

    GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Baidu Netdisk',
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Mega',
    ]);
    GameScreenshot::factory()->for($this->game)->create();
});

test('resource tab pages share hero metadata without shipping every tab payload', function (string $routeName, string $activeTab) {
    $this->get(route($routeName, $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('activeTab', $activeTab)
            ->where('resource.id', $this->game->slug)
            ->where('resource.title', $this->game->title)
            ->where('resource.subtitle', 'A warm countryside love story')
            ->where('resource.developer', $this->game->developer ?? 'Unknown')
            ->where('resource.releaseDate', $this->game->release_date?->toDateString())
            ->where(
                'resource.description',
                $activeTab === 'details'
                    ? fn (string $description): bool => str_contains($description, '<strong>Rich details</strong>') && ! str_contains($description, '<script>')
                    : '',
            )
            ->where('resource.platforms', [
                ['name' => 'Windows', 'slug' => 'windows'],
            ])
            ->where('resource.languages', [
                ['name' => 'Chinese', 'code' => 'zh'],
            ])
            ->where('resource.isFavorited', false)
            ->where('resource.adminEditUrl', null)
            ->where('resource.hasDownloads', true)
            ->where('resourceNotice', '')
        );
})->with([
    'details' => ['resources.details', 'details'],
    'downloads' => ['resources.downloads', 'downloads'],
    'screenshots' => ['resources.screenshots', 'screenshots'],
]);

test('resource pages expose a sanitized site notice above downloads when enabled', function () {
    Setting::setBoolean('resource_notice_enabled', true);
    Setting::set(
        'resource_notice_content',
        '<p>Please use <strong>official mirrors</strong><script>alert(1)</script>.</p>',
    );

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where(
                'resourceNotice',
                fn (string $html): bool => str_contains($html, 'official mirrors')
                    && str_contains($html, '<strong>')
                    && ! str_contains($html, '<script>'),
            )
        );
});

test('resource pages hide the site notice when it is disabled', function () {
    Setting::setBoolean('resource_notice_enabled', false);
    Setting::set('resource_notice_content', '<p>Hidden notice</p>');

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('resourceNotice', '')
        );
});

test('resource tab endpoints keep the active tab contract for direct navigation', function () {
    foreach ([
        'details' => 'resources.details',
        'downloads' => 'resources.downloads',
        'screenshots' => 'resources.screenshots',
        'comments' => 'resources.comments',
    ] as $activeTab => $routeName) {
        $this->get(route($routeName, $this->game->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resources/show')
                ->where('activeTab', $activeTab)
            );
    }
});

test('details tab omits screenshots and full release download payloads', function () {
    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.description', fn (string $description): bool => str_contains($description, '<strong>Rich details</strong>') && ! str_contains($description, '<script>'))
            ->has('resource.detailVersions', 1)
            ->where('resource.detailVersions.0.code', 'original')
            ->where('resource.detailVersions.0.isDefault', true)
            ->where('resource.screenshots', [])
            ->where('resource.releases', [])
        );
});

test('details tab exposes sanitized language versions in configured order', function () {
    $japanese = Language::factory()->create(['name' => 'Japanese', 'code' => 'ja']);
    $english = Language::factory()->create(['name' => 'English', 'code' => 'en']);

    $this->game->detailTranslations()->createMany([
        [
            'language_id' => $japanese->id,
            'description' => '<p>Japanese details<script>alert(1)</script></p>',
            'sort_order' => 20,
        ],
        [
            'language_id' => $english->id,
            'description' => '<p><strong>English details</strong><script>alert(1)</script></p>',
            'sort_order' => 10,
        ],
    ]);

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resource.detailVersions', 3)
            ->where('resource.detailVersions.0.code', 'original')
            ->where('resource.detailVersions.0.name', 'English')
            ->where('resource.detailVersions.0.isDefault', true)
            ->where('resource.detailVersions.1.code', 'en')
            ->where('resource.detailVersions.1.name', 'English')
            ->where(
                'resource.detailVersions.1.html',
                fn (string $html): bool => str_contains($html, '<strong>English details</strong>')
                    && ! str_contains($html, '<script>'),
            )
            ->where('resource.detailVersions.1.isDefault', false)
            ->where('resource.detailVersions.2.code', 'ja')
            ->where(
                'resource.detailVersions.2.html',
                fn (string $html): bool => str_contains($html, 'Japanese details')
                    && ! str_contains($html, '<script>'),
            )
        );
});

test('details hero uses a card thumbnail while cover stays full size', function () {
    Storage::fake(Media::diskName());

    $path = UploadedFile::fake()
        ->image('cover.jpg', 1280, 720)
        ->store('games/covers', Media::diskName());

    $this->game->update([
        'cover_path' => $path,
        'cover_url' => '',
    ]);

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'resource.thumbnail',
                fn (string $thumbnail): bool => str_contains($thumbnail, '/thumbs/'),
            )
            ->where(
                'resource.cover',
                fn (string $cover): bool => str_contains($cover, $path) && ! str_contains($cover, '/thumbs/'),
            )
        );
});

test('downloads tab includes releases and download links without screenshots', function () {
    $this->get(route('resources.downloads', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.description', '')
            ->where('resource.detailVersions', [])
            ->where('resource.screenshots', [])
            ->has('resource.releases', 1)
            ->where('resource.releases.0.title', 'Official release')
            ->where('resource.releases.0.platforms', [
                ['name' => 'Windows', 'slug' => 'windows'],
            ])
            ->where('resource.releases.0.languages', [
                ['name' => 'Chinese', 'code' => 'zh'],
            ])
            ->where(
                'resource.releases.0.description',
                fn (string $description): bool => str_contains($description, '<p>Release notes</p>') && ! str_contains($description, '<script>'),
            )
            ->has('resource.releases.0.downloadLinks', 2)
            ->where('resource.releases.0.downloadLinks.0.label', 'Baidu Netdisk')
        );
});

test('screenshots tab includes screenshots without release download payloads', function () {
    $this->get(route('resources.screenshots', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.description', '')
            ->where('resource.detailVersions', [])
            ->has('resource.screenshots', 1)
            ->where('resource.releases', [])
        );
});

test('administrators receive an edit url on the resource page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'resource.adminEditUrl',
                GameResource::getUrl('edit', ['record' => $this->game], panel: 'admin'),
            )
        );
});

test('regular users do not receive an admin edit url on the resource page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.adminEditUrl', null)
        );
});

test('resource show redirects to the details route', function () {
    $this->get(route('resources.show', $this->game->slug))
        ->assertRedirect(route('resources.details', $this->game->slug));
});

test('resource routes return not found for unknown or unpublished games', function () {
    $draft = Game::factory()->draft()->create();

    $this->get(route('resources.details', 'missing-resource'))->assertNotFound();
    $this->get(route('resources.details', $draft->slug))->assertNotFound();
});

test('home receives only published games', function () {
    Game::factory()->draft()->create(['title' => 'Hidden Draft']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('resources', 1)
            ->where('resources.0.id', $this->game->slug)
            ->where('resources.0.platforms', [
                ['name' => 'Windows', 'slug' => 'windows'],
            ])
            ->where('resources.0.languages', [
                ['name' => 'Chinese', 'code' => 'zh'],
            ])
            ->where('resources.0.version', '1.2 demo')
            ->where('hero.backgroundUrl', Setting::defaultHeroBackgroundUrl())
            ->where('hero.description', Setting::defaultHeroDescription())
            ->missing('hero.eyebrow')
        );
});

test('home uses the configured hero background image', function () {
    Setting::set('hero_background_path', 'site/hero/custom.jpg');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('hero.backgroundUrl', Media::url('site/hero/custom.jpg'))
        );
});

test('home uses configured hero copy from site settings', function () {
    Setting::set('hero_title', 'Custom title');
    Setting::set('hero_description', 'Custom description line.');
    Setting::set('hero_browse_label', 'Explore');
    Setting::set('hero_random_label', 'Surprise');
    Setting::setBoolean('hero_show_random', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('hero.title', 'Custom title')
            ->where('hero.description', 'Custom description line.')
            ->where('hero.browseLabel', 'Explore')
            ->where('hero.randomLabel', 'Surprise')
            ->where('hero.enabled', true)
            ->where('hero.showBrowse', true)
            ->where('hero.showRandom', false)
            ->missing('hero.eyebrow')
        );
});

test('home can hide the hero module from site settings', function () {
    Setting::setBoolean('hero_enabled', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('hero.enabled', false)
        );
});

test('home resources are ordered by published date', function () {
    $this->game->update([
        'release_date' => now()->subMonths(6)->toDateString(),
        'published_at' => now()->subDay(),
    ]);

    $olderPublished = Game::factory()->create([
        'slug' => 'older-published',
        'release_date' => now()->subWeek()->toDateString(),
        'published_at' => now()->subDays(3),
    ]);
    $newestPublished = Game::factory()->create([
        'slug' => 'newest-published',
        'release_date' => now()->subYears(2)->toDateString(),
        'published_at' => now(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->missing('recentReleases')
            ->has('resources', 3)
            ->where('resources.0.id', $newestPublished->slug)
            ->where('resources.1.id', $this->game->slug)
            ->where('resources.2.id', $olderPublished->slug)
        );
});

test('home omits card version when no release version is filled', function () {
    $this->game->releases()->update(['version' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.0.id', $this->game->slug)
            ->where('resources.0.version', null)
        );
});

test('recent resource updates only include games with download updates and order by that time', function () {
    $updatedAt = now()->subHour();
    $this->game->updateQuietly([
        'downloads_updated_at' => now()->subDays(2),
    ]);
    $newer = Game::factory()->create([
        'published_at' => now()->subDay(),
        'downloads_updated_at' => $updatedAt,
    ]);
    Game::factory()->create([
        'published_at' => now(),
        'downloads_updated_at' => null,
    ]);

    $updates = app(ListRecentResourceUpdates::class)();

    expect($updates)->toHaveCount(2)
        ->and($updates[0]['id'])->toBe($newer->slug)
        ->and($updates[0]['downloadsUpdatedAt'])->toBe($updatedAt->toDateString())
        ->and(collect($updates)->pluck('id')->all())->toBe([
            $newer->slug,
            $this->game->slug,
        ]);
});

test('home does not include an updated resources strip', function () {
    $this->game->updateQuietly([
        'downloads_updated_at' => now()->subHour(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('resources')
            ->missing('updatedResources')
        );
});

test('inactive releases or releases without download links do not advertise a platform or language', function () {
    $inactiveRelease = GameRelease::factory()->for($this->game)->create(['is_active' => false]);
    GameDownloadLink::factory()->for($inactiveRelease, 'release')->create();

    $emptyRelease = GameRelease::factory()->for($this->game)->create(['is_active' => true]);

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resource.releases', [])
            ->where('resource.platforms', [
                ['name' => 'Windows', 'slug' => 'windows'],
            ])
            ->where('resource.languages', [
                ['name' => 'Chinese', 'code' => 'zh'],
            ])
        );

    expect($emptyRelease->downloadLinks)->toHaveCount(0);
});
