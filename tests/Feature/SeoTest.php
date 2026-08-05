<?php

use App\Actions\Games\ListPublishedGames;
use App\Models\Category;
use App\Models\Doc;
use App\Models\Game;
use App\Models\Setting;
use App\Models\User;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resource detail pages expose page-level seo props', function () {
    $category = Category::factory()->create(['name' => 'Visual Novel']);
    $game = Game::factory()->create([
        'title' => 'Senren Banka',
        'slug' => 'senren-banka',
        'subtitle' => 'A spring tale',
        'description' => '<p>A published visual novel about spring.</p>',
        'category_id' => $category->id,
        'cover_path' => 'games/covers/senren.png',
    ]);

    $this->get(route('resources.details', $game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('resources/show')
            ->where('pageSeo.title', 'Senren Banka')
            ->where('pageSeo.robots', 'index,follow')
            ->where('pageSeo.canonical', route('resources.details', $game))
            ->where('pageSeo.description', 'A published visual novel about spring.')
            ->where('pageSeo.ogImageUrl', PageSeo::absoluteUrl('/storage/games/covers/senren.png'))
            ->where('pageSeo.jsonLd.@type', 'SoftwareApplication')
            ->where('pageSeo.jsonLd.name', 'Senren Banka')
        );
});

test('resource catalog canonical ignores filter query strings', function () {
    $this->get(route('resources.index', ['q' => 'foo', 'sort' => 'latest']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index'))
            ->where('pageSeo.title', 'Resources')
            ->where('pageSeo.robots', 'noindex,follow')
        );
});

test('unfiltered resource catalog page 1 uses a clean canonical without page query', function () {
    $this->get(route('resources.index', ['page' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index'))
            ->where('pageSeo.title', 'Resources')
        );
});

test('unfiltered resource catalog deep pages self-canonicalize', function () {
    // Two full pages + one item so page 2 is valid (PER_PAGE is 12).
    Game::factory()->count(ListPublishedGames::PER_PAGE + 1)->create();

    $this->get(route('resources.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.canonical', route('resources.index', ['page' => 2]))
            ->where('pageSeo.title', 'Resources · Page 2')
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
            ->where('pageSeo.title', 'Resources')
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
        route('resources.details', $game),
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
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pageSeo.robots', 'noindex,nofollow')
            ->where('pageSeo.title', 'Favorites')
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
        ->and(mb_strlen((string) PageSeo::plainDescription(str_repeat('a', 200))))->toBeLessThanOrEqual(161);
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
        ->toContain('head-key="json-ld"')
        ->toContain('type="application/ld+json"')
        ->toContain("replace(/</g, '\\\\u003c')")
        ->toContain('serializeJsonLd');
});

test('resource catalog partial reloads include page seo', function () {
    $pagination = file_get_contents(resource_path('js/components/site/resource-pagination.tsx'));
    $filters = file_get_contents(resource_path('js/components/site/resource-filter-controls.tsx'));

    expect($pagination)
        ->not->toBeFalse()
        ->toContain("only={['resources', 'filters', 'pageSeo']}");

    expect($filters)
        ->not->toBeFalse()
        ->toContain("only: ['resources', 'filters', 'pageSeo']");
});

test('auth modal layout mounts site-wide SiteSeo once for every page shell', function () {
    $layout = file_get_contents(resource_path('js/layouts/auth-modal-layout.tsx'));

    expect($layout)->not->toBeFalse()
        ->toContain("import { SiteSeo } from '@/components/site/site-seo'")
        ->toContain('<SiteSeo />');
});
