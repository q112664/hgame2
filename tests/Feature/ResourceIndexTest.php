<?php

use App\Actions\Games\ListPublishedGames;
use App\Models\Category;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{game: Game, category: Category, platform: Platform, language: Language, tags: list<Tag>}
 */
function createListedGame(array $overrides = []): array
{
    $category = $overrides['category'] ?? Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $platform = $overrides['platform'] ?? Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);
    $language = $overrides['language'] ?? Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);

    $game = Game::factory()->create([
        'category_id' => $category->id,
        'slug' => $overrides['slug'] ?? 'senren-banka',
        'title' => $overrides['title'] ?? 'Senren Banka',
        'published_at' => $overrides['published_at'] ?? now()->subDay(),
        'views_count' => $overrides['views_count'] ?? 0,
    ]);

    $tags = $overrides['tags'] ?? [
        Tag::factory()->create(['name' => 'Romance', 'slug' => 'romance']),
    ];

    $game->tags()->attach(collect($tags)->pluck('id'));

    $release = GameRelease::factory()->for($game)->create([
        'platform_id' => $platform->id,
        'language_id' => $language->id,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    $release->platforms()->sync([$platform->id]);
    $release->languages()->sync([$language->id]);
    GameDownloadLink::factory()->for($release, 'release')->create();

    return compact('game', 'category', 'platform', 'language', 'tags');
}

test('resources index lists published games with filter options', function () {
    ['game' => $game, 'category' => $category, 'platform' => $platform, 'language' => $language, 'tags' => $tags] = createListedGame();
    Game::factory()->draft()->create(['title' => 'Hidden Draft']);

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->has('resources.data', 1)
            ->where('resources.data.0.id', $game->slug)
            ->where('resources.current_page', 1)
            ->where('filters.category', null)
            ->where('filters.platform', null)
            ->where('filters.language', null)
            ->where('filters.tags', [])
            ->where('filters.sort', 'latest')
            ->where('filterOptions.categories.0.slug', $category->slug)
            ->where('filterOptions.platforms.0.slug', $platform->slug)
            ->where('filterOptions.languages.0.code', $language->code)
            ->where('filterOptions.tags.0.slug', $tags[0]->slug)
        );
});

test('resources index filters by category platform language and tags', function () {
    ['game' => $matched] = createListedGame();

    $otherCategory = Category::factory()->create([
        'name' => 'RPG',
        'slug' => 'rpg',
    ]);
    $otherPlatform = Platform::factory()->create([
        'name' => 'Android',
        'slug' => 'android',
    ]);
    $otherLanguage = Language::factory()->create([
        'name' => 'Japanese',
        'code' => 'ja',
    ]);
    $otherTag = Tag::factory()->create([
        'name' => 'Comedy',
        'slug' => 'comedy',
    ]);

    createListedGame([
        'slug' => 'other-game',
        'title' => 'Other Game',
        'category' => $otherCategory,
        'platform' => $otherPlatform,
        'language' => $otherLanguage,
        'tags' => [$otherTag],
        'published_at' => now()->subHours(2),
    ]);

    $this->get(route('resources.index', [
        'category' => 'visual-novel',
        'platform' => 'windows',
        'language' => 'zh',
        'tags' => ['romance'],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resources.data', 1)
            ->where('resources.data.0.id', $matched->slug)
            ->where('filters.category', 'visual-novel')
            ->where('filters.platform', 'windows')
            ->where('filters.language', 'zh')
            ->where('filters.tags', ['romance'])
        );
});

test('resources index requires all selected tags', function () {
    $romance = Tag::factory()->create(['name' => 'Romance', 'slug' => 'romance']);
    $comedy = Tag::factory()->create(['name' => 'Comedy', 'slug' => 'comedy']);
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $platform = Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);
    $language = Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);

    createListedGame([
        'slug' => 'romance-only',
        'title' => 'Romance Only',
        'category' => $category,
        'platform' => $platform,
        'language' => $language,
        'tags' => [$romance],
    ]);

    ['game' => $both] = createListedGame([
        'slug' => 'romance-comedy',
        'title' => 'Romance Comedy',
        'category' => $category,
        'platform' => $platform,
        'language' => $language,
        'tags' => [$romance, $comedy],
        'published_at' => now()->subHours(3),
    ]);

    $this->get(route('resources.index', [
        'tags' => ['romance', 'comedy'],
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resources.data', 1)
            ->where('resources.data.0.id', $both->slug)
        );
});

test('resources index paginates results', function () {
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $platform = Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);
    $language = Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);

    foreach (range(1, ListPublishedGames::PER_PAGE + 1) as $index) {
        createListedGame([
            'slug' => "game-{$index}",
            'title' => "Game {$index}",
            'category' => $category,
            'platform' => $platform,
            'language' => $language,
            'tags' => [],
            'published_at' => now()->subMinutes($index),
        ]);
    }

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resources.data', ListPublishedGames::PER_PAGE)
            ->where('resources.current_page', 1)
            ->where('resources.last_page', 2)
            ->where('resources.total', ListPublishedGames::PER_PAGE + 1)
        );

    $this->get(route('resources.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('resources.data', 1)
            ->where('resources.current_page', 2)
        );
});

test('resources index rejects unknown filter values', function () {
    $this->get(route('resources.index', [
        'category' => 'missing-category',
    ]))->assertSessionHasErrors('category');
});

test('resources index sorts by title views and oldest', function () {
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);
    $platform = Platform::factory()->create([
        'name' => 'Windows',
        'slug' => 'windows',
    ]);
    $language = Language::factory()->create([
        'name' => 'Chinese',
        'code' => 'zh',
    ]);

    createListedGame([
        'slug' => 'beta-game',
        'title' => 'Beta Game',
        'category' => $category,
        'platform' => $platform,
        'language' => $language,
        'tags' => [],
        'published_at' => now()->subDays(2),
        'views_count' => 10,
    ]);

    createListedGame([
        'slug' => 'alpha-game',
        'title' => 'Alpha Game',
        'category' => $category,
        'platform' => $platform,
        'language' => $language,
        'tags' => [],
        'published_at' => now()->subDay(),
        'views_count' => 100,
    ]);

    $this->get(route('resources.index', ['sort' => 'title']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'title')
            ->where('resources.data.0.id', 'alpha-game')
            ->where('resources.data.1.id', 'beta-game')
        );

    $this->get(route('resources.index', ['sort' => 'views']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'views')
            ->where('resources.data.0.id', 'alpha-game')
            ->where('resources.data.1.id', 'beta-game')
        );

    $this->get(route('resources.index', ['sort' => 'oldest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'oldest')
            ->where('resources.data.0.id', 'beta-game')
            ->where('resources.data.1.id', 'alpha-game')
        );
});

test('resources index rejects unknown sort values', function () {
    $this->get(route('resources.index', [
        'sort' => 'popularity',
    ]))->assertSessionHasErrors('sort');
});

test('resources index skips filter options on partial reload', function () {
    createListedGame();

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->has('filterOptions.categories')
            ->has('filterOptions.platforms')
            ->has('filterOptions.languages')
            ->has('filterOptions.tags')
        );

    $queriedFilterOptionTables = [];

    DB::listen(function ($query) use (&$queriedFilterOptionTables): void {
        $sql = strtolower($query->sql);

        if (
            str_contains($sql, ' from "categories"')
            && str_contains($sql, 'exists')
        ) {
            $queriedFilterOptionTables[] = 'categories';
        }

        if (
            str_contains($sql, ' from "platforms"')
            && str_contains($sql, 'exists')
        ) {
            $queriedFilterOptionTables[] = 'platforms';
        }

        if (
            str_contains($sql, ' from "languages"')
            && str_contains($sql, 'exists')
        ) {
            $queriedFilterOptionTables[] = 'languages';
        }

        if (
            str_contains($sql, ' from "tags"')
            && str_contains($sql, 'exists')
        ) {
            $queriedFilterOptionTables[] = 'tags';
        }
    });

    $partial = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'resources/index',
        'X-Inertia-Partial-Data' => 'resources,filters',
    ])->get(route('resources.index', [
        'sort' => 'title',
    ]));

    $partial->assertOk()
        ->assertJsonPath('component', 'resources/index')
        ->assertJsonPath('props.filters.sort', 'title')
        ->assertJsonMissingPath('props.filterOptions');

    expect($queriedFilterOptionTables)->toBeEmpty();
});

test('resources index platform and language options only include available releases', function () {
    $category = Category::factory()->create([
        'name' => 'Visual Novel',
        'slug' => 'visual-novel',
    ]);

    ['platform' => $availablePlatform, 'language' => $availableLanguage] = createListedGame([
        'category' => $category,
        'slug' => 'available-game',
        'title' => 'Available Game',
        'tags' => [],
    ]);

    $game = Game::factory()->create([
        'category_id' => $category->id,
        'slug' => 'unavailable-releases',
        'title' => 'Unavailable Releases',
        'published_at' => now()->subDay(),
    ]);

    $inactivePlatform = Platform::factory()->create([
        'name' => 'Android',
        'slug' => 'android',
    ]);
    $futureLanguage = Language::factory()->create([
        'name' => 'Japanese',
        'code' => 'ja',
    ]);
    $emptyPlatform = Platform::factory()->create([
        'name' => 'iOS',
        'slug' => 'ios',
    ]);
    $emptyLanguage = Language::factory()->create([
        'name' => 'English',
        'code' => 'en',
    ]);

    $inactiveRelease = GameRelease::factory()->for($game)->create([
        'platform_id' => $inactivePlatform->id,
        'language_id' => $futureLanguage->id,
        'is_active' => false,
        'published_at' => now()->subDay(),
    ]);
    $inactiveRelease->platforms()->sync([$inactivePlatform->id]);
    $inactiveRelease->languages()->sync([$futureLanguage->id]);
    GameDownloadLink::factory()->for($inactiveRelease, 'release')->create();

    $futureRelease = GameRelease::factory()->for($game)->create([
        'platform_id' => $inactivePlatform->id,
        'language_id' => $futureLanguage->id,
        'is_active' => true,
        'published_at' => now()->addDay(),
    ]);
    $futureRelease->platforms()->sync([$inactivePlatform->id]);
    $futureRelease->languages()->sync([$futureLanguage->id]);
    GameDownloadLink::factory()->for($futureRelease, 'release')->create();

    $emptyRelease = GameRelease::factory()->for($game)->create([
        'platform_id' => $emptyPlatform->id,
        'language_id' => $emptyLanguage->id,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    $emptyRelease->platforms()->sync([$emptyPlatform->id]);
    $emptyRelease->languages()->sync([$emptyLanguage->id]);

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions.platforms', 1)
            ->where('filterOptions.platforms.0.slug', $availablePlatform->slug)
            ->has('filterOptions.languages', 1)
            ->where('filterOptions.languages.0.code', $availableLanguage->code)
        );
});
