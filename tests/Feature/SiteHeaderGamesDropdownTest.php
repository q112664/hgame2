<?php

use App\Models\Category;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Support\TaxonomyDirectory;
use Illuminate\Filesystem\Filesystem;
use Inertia\Testing\AssertableInertia as Assert;

test('the site header wires independent genre and language flyouts', function () {
    $source = app(Filesystem::class)->get(resource_path('js/components/site/site-header.tsx'));

    expect($source)
        ->toContain('function TaxonomyFlyout')
        ->toContain('label="Genres"')
        ->toContain('label="Languages"')
        ->toContain('resourcesGenre.url(item.value)')
        ->toContain('resourcesLanguage.url(item.value)')
        ->toContain('aria-haspopup="menu"')
        ->toContain('role="menu"')
        ->toContain('CollapsibleTrigger')
        ->not->toContain('icon={Library}')
        ->not->toContain('icon={Languages}')
        ->not->toContain('staticGameCategories')
        ->not->toContain('function GamesCategoryMenu')
        ->not->toContain('onPointerEnter={openMenu}')
        ->toContain('aria-expanded:bg-primary/12')
        ->toContain('aria-expanded:text-primary')
        ->toContain("menuItemPath(item.url) === '/games'")
        ->toContain('activePrefix="/games/genre"')
        ->toContain('activePrefix="/games/language"');
});

test('published genres and languages are shared for the header flyouts', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    $language = Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);
    $game = Game::factory()->create([
        'category_id' => $category->id,
        'published_at' => now()->subDay(),
    ]);
    $release = GameRelease::factory()->for($game)->create([
        'language_id' => $language->id,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    TaxonomyDirectory::forget();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('taxonomyNav.categories', 1)
            ->where('taxonomyNav.categories.0.name', 'SLG')
            ->where('taxonomyNav.categories.0.value', 'slg')
            ->has('taxonomyNav.languages', 1)
            ->where('taxonomyNav.languages.0.name', 'Chinese')
            ->where('taxonomyNav.languages.0.value', 'zh')
        );
});

test('genre and language landing pages are reachable from the header flyouts', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    $language = Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);
    $game = Game::factory()->create([
        'category_id' => $category->id,
        'published_at' => now()->subDay(),
    ]);
    $release = GameRelease::factory()->for($game)->create([
        'language_id' => $language->id,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    $this->get(route('resources.genre', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->where('taxonomy.type', 'category')
            ->where('taxonomy.value', 'slg')
        );

    $this->get(route('resources.language', $language))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->where('taxonomy.type', 'language')
            ->where('taxonomy.value', 'zh')
        );
});
