<?php

use App\Actions\Games\ListPublishedGames;
use App\Models\Category;
use App\Models\Doc;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\GameScreenshot;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\Media;
use App\Support\PageSeo;
use App\Support\TaxonomyDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resource detail pages expose page-level seo props', function () {
    $category = Category::factory()->create(['name' => 'Visual Novel']);
    $sitePublishedAt = now()->subDays(3)->startOfSecond();
    $downloadsUpdatedAt = now()->subDay()->startOfSecond();
    $commercialRelease = now()->subYears(5)->toDateString();
    $game = Game::factory()->create([
        'title' => 'Senren Banka',
        'slug' => 'senren-banka',
        'subtitle' => 'A spring tale',
        'description' => '<p>A published visual novel about spring.</p>',
        'category_id' => $category->id,
        'cover_path' => 'games/covers/senren.png',
        'release_date' => $commercialRelease,
        'published_at' => $sitePublishedAt,
        'downloads_updated_at' => $downloadsUpdatedAt,
    ]);
    // Eloquent updated_at must not drive SEO modified time.
    $game->forceFill(['updated_at' => now()])->saveQuietly();

    $this->get(route('resources.show', $game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resources/show')
            ->where('pageSeo.title', 'Senren Banka')
            ->where('pageSeo.robots', 'index,follow')
            ->where('pageSeo.canonical', route('resources.show', $game))
            ->where(
                'pageSeo.description',
                fn (string $description): bool => str_starts_with($description, 'Download Senren Banka')
                    && str_contains($description, 'Visual Novel')
                    && str_contains(mb_strtolower($description), 'download')
                    && mb_strlen($description) >= 80
                    && mb_strlen($description) <= PageSeo::META_DESCRIPTION_MAX,
            )
            ->where(
                'pageSeo.jsonLd.@graph.0.description',
                fn (string $description): bool => str_starts_with($description, 'Download Senren Banka'),
            )
            ->where('pageSeo.ogImageUrl', PageSeo::absoluteUrl('/storage/games/covers/senren.png'))
            ->where('pageSeo.publishedTime', $sitePublishedAt->toIso8601String())
            ->where('pageSeo.modifiedTime', $downloadsUpdatedAt->toIso8601String())
            ->where('pageSeo.jsonLd.@graph.0.@type', 'SoftwareApplication')
            ->where('pageSeo.jsonLd.@graph.0.name', 'Senren Banka')
            ->where('pageSeo.jsonLd.@graph.0.alternateName', 'A spring tale')
            ->missing('pageSeo.jsonLd.@graph.0.aggregateRating')
            ->missing('pageSeo.jsonLd.@graph.0.screenshot')
            // Crawlers use site publish time, not commercial release_date.
            ->where('pageSeo.jsonLd.@graph.0.datePublished', $sitePublishedAt->toIso8601String())
            ->where('pageSeo.jsonLd.@graph.0.dateModified', $downloadsUpdatedAt->toIso8601String())
            ->where('pageSeo.jsonLd.@graph.1.@type', 'BreadcrumbList')
            ->where('pageSeo.jsonLd.@graph.1.itemListElement.0.name', 'Home')
            ->where('pageSeo.jsonLd.@graph.1.itemListElement.1.name', 'Games')
            ->where('pageSeo.jsonLd.@graph.1.itemListElement.2.name', 'Visual Novel')
            ->where(
                'pageSeo.jsonLd.@graph.1.itemListElement.2.item',
                route('resources.genre', $category),
            )
            ->where('pageSeo.jsonLd.@graph.1.itemListElement.3.name', 'Senren Banka')
            ->where(
                'pageSeo.jsonLd.@graph.1.itemListElement.3.item',
                route('resources.show', $game),
            )
            ->where('resource.publishedAt', $sitePublishedAt->toDateString())
            ->where('resource.downloadsUpdatedAt', $downloadsUpdatedAt->toDateString())
            ->where('resource.releaseDate', $commercialRelease)
        );
});

test('resource detail json-ld includes screenshots languages ratings and genre breadcrumbs', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    $game = Game::factory()->create([
        'title' => 'Schema Game',
        'slug' => 'schema-game',
        'subtitle' => 'スキーマゲーム',
        'category_id' => $category->id,
        'cover_path' => 'games/covers/schema.png',
        'ratings_count' => 4,
        'ratings_avg' => 4.5,
    ]);
    $windows = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $japanese = Language::factory()->create(['name' => 'Japanese', 'code' => 'ja']);
    $english = Language::factory()->create(['name' => 'English', 'code' => 'en']);
    $release = GameRelease::factory()->for($game)->create([
        'platform_id' => $windows->id,
        'language_id' => $japanese->id,
    ]);
    $release->languages()->sync([$japanese->id, $english->id]);
    GameDownloadLink::factory()->for($release, 'release')->create();
    GameScreenshot::factory()->for($game)->create([
        'path' => 'games/screenshots/one.jpg',
        'url' => '',
        'sort_order' => 0,
    ]);

    $this->get(route('resources.show', $game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.jsonLd.@graph.0.@type', 'SoftwareApplication')
            ->where('pageSeo.jsonLd.@graph.0.alternateName', 'スキーマゲーム')
            ->where('pageSeo.jsonLd.@graph.0.genre', 'SLG')
            ->where('pageSeo.jsonLd.@graph.0.operatingSystem', 'Windows')
            ->where(
                'pageSeo.jsonLd.@graph.0.inLanguage',
                fn ($codes): bool => collect($codes)->sort()->values()->all() === ['en', 'ja'],
            )
            ->where('pageSeo.jsonLd.@graph.0.screenshot', [
                Media::url('games/screenshots/one.jpg'),
            ])
            ->where('pageSeo.jsonLd.@graph.0.aggregateRating.@type', 'AggregateRating')
            ->where('pageSeo.jsonLd.@graph.0.aggregateRating.ratingValue', 4.5)
            ->where('pageSeo.jsonLd.@graph.0.aggregateRating.ratingCount', 4)
            ->where('pageSeo.jsonLd.@graph.0.aggregateRating.bestRating', 5)
            ->where('pageSeo.jsonLd.@graph.0.aggregateRating.worstRating', 1)
            ->where('pageSeo.jsonLd.@graph.1.@type', 'BreadcrumbList')
            ->has('pageSeo.jsonLd.@graph.1.itemListElement', 4)
            ->where('pageSeo.jsonLd.@graph.1.itemListElement.2.name', 'SLG')
            ->where(
                'pageSeo.jsonLd.@graph.1.itemListElement.2.item',
                route('resources.genre', $category),
            )
            ->where('pageSeo.jsonLd.@graph.1.itemListElement.3.name', 'Schema Game')
        );
});

test('legacy details urls permanently redirect to the canonical resource url', function () {
    $game = Game::factory()->create(['slug' => 'legacy-details-game']);

    $this->get(route('resources.details', $game))
        ->assertStatus(301)
        ->assertRedirect(route('resources.show', $game));
});

test('legacy resource tab urls permanently redirect to the canonical resource url', function () {
    $game = Game::factory()->create([
        'slug' => 'tab-cluster-game',
        'title' => 'Tab Cluster Game',
    ]);

    foreach ([
        'resources.downloads' => 'downloads',
        'resources.screenshots' => 'screenshots',
        'resources.comments' => 'comments',
    ] as $route => $fragment) {
        $this->get(route($route, $game))
            ->assertStatus(301)
            ->assertRedirect(route('resources.show', $game).'#'.$fragment);
    }
});

test('paginated comments on the details page are noindex and canonical to details', function () {
    $game = Game::factory()->create([
        'slug' => 'paged-comments-game',
        'title' => 'Paged Comments Game',
    ]);
    $user = User::factory()->create();
    GameComment::factory()
        ->count(21)
        ->for($game)
        ->for($user)
        ->create();

    $this->get(route('resources.show', ['resource' => $game, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.title', 'Paged Comments Game')
            ->where('pageSeo.robots', 'noindex,follow')
            ->where('pageSeo.canonical', route('resources.show', $game))
            ->where('pageSeo.jsonLd', null)
            ->where('comments.current_page', 2)
        );
});

test('resource catalog canonical ignores filter query strings', function () {
    $this->get(route('resources.index', ['q' => 'foo', 'sort' => 'latest']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index'))
            ->where('pageSeo.title', 'Hentai Games & Eroge Downloads')
            ->where('pageSeo.titleSuffix', Setting::siteLogoText())
            ->where(
                'pageSeo.description',
                fn (string $description): bool => str_contains(mb_strtolower($description), 'hentai')
                    && str_contains(mb_strtolower($description), 'download')
                    && mb_strlen($description) >= PageSeo::META_DESCRIPTION_MIN
                    && mb_strlen($description) <= PageSeo::META_DESCRIPTION_MAX,
            )
            ->where('pageSeo.robots', 'noindex,follow')
        );
});

test('single category query redirects to genre taxonomy path', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);

    $this->get(route('resources.index', ['category' => $category->slug]))
        ->assertStatus(301)
        ->assertRedirect(route('resources.genre', $category));
});

test('legacy /resources category query permanently redirects in one hop', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);

    $this->get('/resources?category='.$category->slug)
        ->assertStatus(301)
        ->assertRedirect(route('resources.genre', $category));

    $this->get('/resources?category='.$category->slug.'&page=2')
        ->assertStatus(301)
        ->assertRedirect(route('resources.genre', [
            'category' => $category,
            'page' => 2,
        ]));
});

test('genre taxonomy pages are indexable with self-canonical', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    Game::factory()->create(['category_id' => $category->id]);

    $this->get(route('resources.genre', $category))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resources/index')
            ->where('heading', 'SLG Hentai Games & Eroge')
            ->where('resultsHeading', 'SLG games')
            ->where('taxonomy.type', 'category')
            ->where('taxonomy.value', 'slg')
            ->where('filters.category', 'slg')
            ->where('pageSeo.title', 'SLG Hentai Games & Eroge')
            ->where(
                'pageSeo.description',
                fn (string $description): bool => str_contains($description, 'SLG')
                    && str_contains(mb_strtolower($description), 'download')
                    && mb_strlen($description) >= PageSeo::META_DESCRIPTION_MIN
                    && mb_strlen($description) <= PageSeo::META_DESCRIPTION_MAX,
            )
            ->where('pageSeo.canonical', route('resources.genre', $category))
            ->where('pageSeo.robots', 'index,follow')
        );
});

test('tag taxonomy pages with enough games are indexable', function () {
    $tag = Tag::factory()->create([
        'name' => 'NTR',
        'slug' => 'ntr',
    ]);
    $games = Game::factory()->count(TaxonomyDirectory::MinPublishedGamesForIndex)->create();
    $tag->games()->attach($games->pluck('id'));

    $this->get(route('resources.tag', $tag))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.title', 'NTR Hentai Games & Eroge')
            ->where('pageSeo.canonical', route('resources.tag', $tag))
            ->where('pageSeo.robots', 'index,follow')
            ->where('filters.tags', ['ntr'])
        );
});

test('thin tag taxonomy pages are noindex', function () {
    $tag = Tag::factory()->create([
        'name' => 'Ahegao',
        'slug' => 'ahegao',
    ]);
    $game = Game::factory()->create();
    $game->tags()->attach($tag);

    $this->get(route('resources.tag', $tag))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,follow')
            ->where('pageSeo.canonical', route('resources.tag', $tag))
        );
});

test('game meta description strips synopsis labels and block-tag glue', function () {
    $cases = [
        [
            'html' => '<p><strong>Synopsis (AI-translated English)</strong></p><p>Play a puzzle game with friends.</p>',
            'starts' => 'Play a puzzle game',
        ],
        [
            'html' => '<p>◆Game Story</p><p>You run into a mysterious girl.</p>',
            'starts' => 'You run into a mysterious girl',
        ],
        [
            'html' => '<h3>Story</h3><p>You are an ordinary office worker.</p>',
            'starts' => 'You are an ordinary office worker',
        ],
    ];

    foreach ($cases as $index => $case) {
        $game = Game::factory()->create([
            'slug' => 'meta-desc-'.$index,
            'description' => $case['html'],
        ]);

        $this->get(route('resources.show', $game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where(
                    'pageSeo.description',
                    fn (string $description): bool => str_contains($description, $case['starts'])
                        && str_starts_with($description, 'Download ')
                        && ! str_contains($description, 'Synopsis (AI-translated English)Play')
                        && ! str_contains($description, 'StoryYou')
                        && ! str_ends_with($description, 'malic…'),
                )
            );
    }
});

test('home page exposes WebSite JSON-LD with SearchAction', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.jsonLd.@type', 'WebSite')
            ->where('pageSeo.jsonLd.potentialAction.@type', 'SearchAction')
            ->where(
                'pageSeo.jsonLd.potentialAction.target.urlTemplate',
                fn (string $template): bool => str_contains($template, '/search?q={search_term_string}'),
            )
        );
});

test('taxonomy navigation is shared for internal catalog links', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    Game::factory()->create(['category_id' => $category->id]);
    TaxonomyDirectory::forget();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('taxonomyNav.categories', 1)
            ->where('taxonomyNav.categories.0.value', 'slg')
            ->where('taxonomyNav.categories.0.name', 'SLG')
        );

    $this->get(route('resources.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('taxonomyNav.categories', 1)
            ->where('taxonomyNav.categories.0.value', 'slg')
        );
});

test('tags index page lists tags with counts and is indexable', function () {
    $tag = Tag::factory()->create([
        'name' => 'Romance',
        'slug' => 'romance',
    ]);
    $game = Game::factory()->create();
    $game->tags()->attach($tag);
    TaxonomyDirectory::forget();

    $this->get(route('resources.tags'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resources/tags')
            ->where('pageSeo.title', 'Game Tags')
            ->where('pageSeo.canonical', route('resources.tags'))
            ->where('pageSeo.robots', 'index,follow')
            ->has('tags', 1)
            ->where('tags.0.value', 'romance')
            ->where('tags.0.name', 'Romance')
            ->where('tags.0.count', 1)
        );
});

test('unfiltered resource catalog page 1 uses a clean canonical without page query', function () {
    $this->get(route('resources.index', ['page' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index'))
            ->where('pageSeo.title', 'Hentai Games & Eroge Downloads')
            ->where('pageSeo.robots', 'index,follow')
        );
});

test('unfiltered resource catalog deep pages are noindex with a unique title', function () {
    // Two full pages + one item so page 2 is valid (PER_PAGE is 12).
    Game::factory()->count(ListPublishedGames::PER_PAGE + 1)->create();

    $this->get(route('resources.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index', ['page' => 2]))
            ->where('pageSeo.title', 'Hentai Games & Eroge Downloads - Page 2')
            ->where('pageSeo.robots', 'noindex,follow')
            ->where(
                'pageSeo.description',
                fn (string $description): bool => str_contains($description, 'Page 2')
                    && $description !== PageSeo::resourcesIndex(1)['description'],
            )
        );
});

test('taxonomy deep pages are noindex with a unique title', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    Game::factory()->count(ListPublishedGames::PER_PAGE + 1)->create([
        'category_id' => $category->id,
    ]);

    $this->get(route('resources.genre', ['category' => $category, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.title', 'SLG Hentai Games & Eroge - Page 2')
            ->where('pageSeo.robots', 'noindex,follow')
            ->where(
                'pageSeo.description',
                fn (string $description): bool => str_contains($description, 'Page 2')
                    && str_contains($description, 'SLG'),
            )
        );
});

test('filtered resource catalog deep pages still fold to the clean catalog', function () {
    Game::factory()->count(ListPublishedGames::PER_PAGE + 1)->create([
        'title' => 'Spring Tale Resource',
    ]);

    $this->get(route('resources.index', ['page' => 2, 'q' => 'Spring', 'sort' => 'views']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index'))
            ->where('pageSeo.title', 'Hentai Games & Eroge Downloads')
            ->where('pageSeo.robots', 'noindex,follow')
        );
});

test('public pages inherit the site-wide robots setting', function () {
    Setting::set('seo_robots', 'noindex,follow');

    $game = Game::factory()->create();
    $doc = Doc::factory()->create();

    $publicPages = [
        route('home'),
        route('resources.index'),
        route('resources.show', $game),
        route('docs.index'),
        route('docs.show', $doc),
    ];

    foreach ($publicPages as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pageSeo.robots', 'noindex,follow')
            );
    }

    Setting::set('seo_robots', 'index,follow');
});

test('page robots rules cannot relax the site-wide robots setting', function () {
    Setting::set('seo_robots', 'noindex,nofollow');

    $this->get(route('resources.index', ['q' => 'spring']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,nofollow')
        );

    $this->get(route('search', ['q' => 'spring']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,nofollow')
        );

    Setting::set('seo_robots', 'index,follow');
});

test('resource catalog pages beyond the last page return 404', function () {
    Game::factory()->count(2)->create();

    $this->get(route('resources.index', ['page' => 999999]))
        ->assertNotFound();

    $this->get(route('resources.index', ['page' => 2]))
        ->assertNotFound();
});

test('search pages are noindexed', function () {
    $this->get(route('search', ['q' => 'test']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,follow')
            ->where('pageSeo.title', 'Search')
        );
});

test('private pages send noindex page seo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('favorites.index'))
        ->assertRedirect(route('users.favorites', $user));

    $this->actingAs($user)
        ->get(route('users.favorites', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,nofollow')
            ->where('pageSeo.title', $user->name.' · Favorites')
        );

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,nofollow')
        );

    $this->post(route('logout'));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,nofollow')
            ->where('pageSeo.title', 'Log in')
        );
});

test('published docs expose article seo', function () {
    $doc = Doc::factory()->create([
        'title' => 'Getting started',
        'slug' => 'getting-started',
        'excerpt' => 'How to use the site.',
    ]);

    $this->get(route('docs.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.title', 'Getting started')
            ->where('pageSeo.description', 'How to use the site.')
            ->where('pageSeo.canonical', route('docs.show', $doc))
            ->where('pageSeo.ogType', 'article')
            ->where('pageSeo.jsonLd.@type', 'Article')
        );
});

test('page seo plain description strips tags and limits length', function () {
    expect(PageSeo::plainDescription('<p>Hello <strong>world</strong></p>'))
        ->toBe('Hello world')
        ->and(PageSeo::plainDescription(str_repeat('a', 200)))
        ->toEndWith('…')
        ->and(mb_strlen((string) PageSeo::plainDescription(str_repeat('a', 200))))->toBeLessThanOrEqual(PageSeo::META_DESCRIPTION_MAX);
});

test('meta descriptions stop at a sentence boundary instead of mid-word', function () {
    $first = 'The heroine enters a labyrinth seething with lust and malice.';
    $second = 'She must survive every trap before dawn.';
    $text = $first.' '.$second.' '.str_repeat('More words about the dungeon. ', 8);

    $limited = PageSeo::limitAtSentence($text);

    expect($limited)
        ->toEndWith('.')
        ->not->toContain('malic…')
        ->and(mb_strlen($limited))->toBeLessThanOrEqual(PageSeo::META_DESCRIPTION_MAX)
        ->and(str_starts_with($limited, $first))->toBeTrue();
});

test('game meta descriptions include keywords and a download call to action', function () {
    $category = Category::factory()->create(['name' => 'Visual Novel']);
    $game = Game::factory()->create([
        'title' => 'Senren Banka',
        'developer' => 'Yuzu Soft',
        'category_id' => $category->id,
        'description' => '<p>The heroine enters a labyrinth seething with lust and malice. She must survive every trap.</p>',
    ]);

    $description = PageSeo::gameDescription($game);

    expect($description)
        ->toStartWith('Download Senren Banka')
        ->toContain('Visual Novel')
        ->toContain('Yuzu Soft')
        ->not->toContain('malic…')
        ->and(mb_strlen($description))->toBeGreaterThanOrEqual(80)
        ->and(mb_strlen($description))->toBeLessThanOrEqual(PageSeo::META_DESCRIPTION_MAX);

    $downloads = PageSeo::gameDescription($game, 'downloads');
    $screenshots = PageSeo::gameDescription($game, 'screenshots');

    expect($downloads)->not->toBe($description)
        ->and($screenshots)->not->toBe($description)
        ->and($downloads)->toContain('Download Senren Banka');
});

test('home meta description is a full-length default when the saved one is too short', function () {
    Setting::set('seo_description', 'Free download hentai games & eroge.');

    $seo = PageSeo::home();
    $description = (string) $seo['description'];

    expect($description)
        ->toContain('Discover')
        ->not->toBe(PageSeo::resourcesIndex()['description'])
        ->and(mb_strlen($description))->toBeGreaterThanOrEqual(PageSeo::META_DESCRIPTION_MIN)
        ->and(mb_strlen($description))->toBeLessThanOrEqual(PageSeo::META_DESCRIPTION_MAX)
        ->and($seo['jsonLd']['description'] ?? null)->toBe($description);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.description', $description)
        );
});

test('home and catalog meta descriptions stay distinct', function () {
    expect(PageSeo::home()['description'])
        ->not->toBe(PageSeo::resourcesIndex()['description'])
        ->and(PageSeo::home()['title'])->toBeNull()
        ->and(PageSeo::resourcesIndex()['title'])->toBe('Hentai Games & Eroge Downloads');
});

test('root blade csr fallback seo tags use data-inertia keys matching react head-key', function () {
    $blade = file_get_contents(resource_path('views/app.blade.php'));
    $site = file_get_contents(resource_path('js/components/site/site-seo.tsx'));

    expect($blade)->not->toBeFalse();
    expect($site)->not->toBeFalse();

    // Favicon must only live inside x-inertia::head (not as a free-floating <link> before it).
    $beforeHead = str($blade)->before('<x-inertia::head>')->toString();
    expect($beforeHead)
        ->not->toContain('rel="icon"')
        ->not->toContain('rel="apple-touch-icon"');

    $sharedKeys = [
        'favicon',
        'apple-touch-icon',
        'description',
        'keywords',
        'robots',
        'og:site_name',
        'og:type',
        'og:title',
        'og:description',
        'og:image',
        'twitter:card',
        'twitter:title',
        'twitter:description',
        'twitter:image',
        'google-site-verification',
    ];

    foreach ($sharedKeys as $key) {
        expect($blade)->toContain('data-inertia="'.$key.'"');
        expect($site)->toContain('head-key="'.$key.'"');
    }

    expect($blade)
        ->toContain('<x-inertia::head>')
        ->toContain('<title>')
        ->not->toContain('application/ld+json')
        ->not->toContain('data-inertia="canonical"')
        ->not->toContain('data-inertia="json-ld"');
});

test('site and page seo components share stable head-keys for inertia dedupe', function () {
    $site = file_get_contents(resource_path('js/components/site/site-seo.tsx'));
    $page = file_get_contents(resource_path('js/components/site/page-seo.tsx'));

    expect($site)->not->toBeFalse();
    expect($page)->not->toBeFalse();

    expect($site)
        ->toContain('export function SiteSeo')
        ->toContain('head-key="favicon"')
        ->toContain('head-key="apple-touch-icon"')
        ->toContain('head-key="description"')
        ->toContain('head-key="robots"')
        ->toContain('head-key="og:image"')
        ->toContain('head-key="og:title"')
        ->toContain('head-key="twitter:title"')
        ->not->toContain('application/ld+json');

    expect($page)
        ->toContain('export function PageSeo')
        ->toContain('head-key="description"')
        ->toContain('head-key="robots"')
        ->toContain('head-key="canonical"')
        ->toContain('head-key="og:image"')
        ->toContain('head-key="twitter:card"')
        ->toContain('head-key="twitter:image"')
        ->toContain('head-key="json-ld"')
        ->toContain('type="application/ld+json"')
        ->toContain("replace(/</g, '\\\\u003c')")
        ->toContain('serializeJsonLd')
        ->not->toContain('<>')
        ->not->toContain('</>')
        ->not->toContain('<Fragment')
        ->not->toContain('React.Fragment');
});

test('resource catalog partial reloads include page seo', function () {
    $pagination = file_get_contents(resource_path('js/components/site/resource-pagination.tsx'));
    $filters = file_get_contents(resource_path('js/components/site/resource-filter-controls.tsx'));

    expect($pagination)
        ->not->toBeFalse()
        ->toContain("'pageSeo'")
        ->toContain("'heading'")
        ->toContain("'taxonomy'");

    expect($filters)
        ->not->toBeFalse()
        ->toContain("'pageSeo'")
        ->toContain("'heading'")
        ->toContain("'taxonomy'");
});

test('auth modal layout mounts site-wide SiteSeo once for every page shell', function () {
    $layout = file_get_contents(resource_path('js/layouts/auth-modal-layout.tsx'));

    expect($layout)->not->toBeFalse()
        ->toContain("import { SiteSeo } from '@/components/site/site-seo'")
        ->toContain('<SiteSeo />');
});
