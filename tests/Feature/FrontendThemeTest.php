<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

test('the shared theme defines light and dark semantic tokens', function () {
    $filesystem = app(Filesystem::class);
    $stylesheet = $filesystem->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain(':root')
        ->toContain('.dark')
        ->toContain('Gallery Dark');

    foreach (
        [
            'background',
            'foreground',
            'primary',
            'muted',
            'border',
            'ring',
            'brand',
            'info',
            'success',
            'warning',
            'surface-sunken',
            'surface-raised',
            'surface-inverse',
            'surface-inverse-foreground',
        ] as $token
    ) {
        expect($stylesheet)->toContain("--{$token}:");
    }
});

test('gallery dark theme uses warm oklch tokens and default radius', function () {
    $stylesheet = app(Filesystem::class)->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('Gallery Dark')
        // Neutral light page (#fafafa, the shionlib canvas) rather than the warm wash.
        ->toContain('--background: oklch(0.985 0 0);')
        ->toContain('--card: oklch(0.995 0.002 60);')
        ->toContain('--primary: oklch(0.565 0.185 27);')
        ->toContain('--accent: oklch(0.955 0.015 30);')
        ->toContain('--radius: 0.625rem;')
        ->toContain('--background: oklch(0.145 0.008 45);')
        ->toContain('--primary: oklch(0.62 0.175 27);')
        ->toContain('--accent: oklch(0.28 0.035 27);')
        ->toContain("--font-sans: 'Inter Variable', sans-serif;")
        ->toContain('--radius-sm: calc(var(--radius) - 4px);')
        ->not->toContain('#3498db')
        ->not->toContain('#3a4d73')
        ->not->toContain('#f4f5f7');
});

test('brand and auth alias the primary accent instead of duplicating it', function () {
    $stylesheet = app(Filesystem::class)->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('--brand: var(--primary);')
        ->toContain('--auth: var(--primary);')
        ->toContain('--brand-foreground: var(--primary-foreground);')
        ->toContain('--auth-foreground: var(--primary-foreground);')
        // The old preset hardcoded the same accent three separate times.
        ->not->toContain('--brand: oklch(')
        ->not->toContain('--auth: oklch(');
});

test('headings use the preloaded instrument sans face', function () {
    $stylesheet = app(Filesystem::class)->get(resource_path('css/app.css'));

    expect($stylesheet)->toContain(
        "--font-heading: 'Instrument Sans', 'Inter Variable', sans-serif;",
    );
});

test('the inertia progress bar follows the theme primary color', function () {
    $app = app(Filesystem::class)->get(resource_path('js/app.tsx'));

    // A literal gray here would ignore the warm accent defined in app.css.
    expect($app)
        ->toContain("color: 'var(--primary)'")
        ->not->toContain('#4B5563');
});

test('the image lightbox is code-split instead of loaded on every detail view', function () {
    $source = app(Filesystem::class)->get(
        resource_path('js/pages/resources/show.tsx'),
    );

    // Static value import would pull the ~76 KB lightbox vendor into the
    // detail-page chunk for every visitor, whether or not they open a screenshot.
    expect($source)
        ->not->toContain("import { ImageLightbox } from '@/components/site/image-lightbox';")
        ->toContain("import type { LightboxSlide } from '@/components/site/image-lightbox';")
        ->toContain('lazy(loadImageLightbox)')
        ->toContain("import('@/components/site/image-lightbox')")
        ->toContain('<Suspense fallback={null}>')
        // Rendered only once an index is set, so SSR emits no lightbox markup.
        ->toContain('{lightboxIndex >= 0 ? (');
});

test('instrument sans declares latin faces without preloading them', function () {
    $config = app(Filesystem::class)->get(base_path('vite.config.ts'));

    // The plugin declares a legacy `woff` face after each `woff2` one with the
    // same unicode-range, so the browser renders the woff and preloading the
    // woff2 only added downloads that nothing ever used.
    expect($config)
        ->toContain("bunny('Instrument Sans'")
        ->toContain("subsets: ['latin']")
        ->toContain('weights: [400, 500, 600]')
        ->toContain('preload: false');
});

test('auth forms use the flat auth button variant', function () {
    $filesystem = app(Filesystem::class);
    $button = $filesystem->get(resource_path('js/components/ui/button.tsx'));

    expect($button)
        ->toContain('auth:')
        ->toContain('bg-auth text-auth-foreground');

    foreach (
        [
            'components/auth/login-form.tsx',
            'components/auth/register-form.tsx',
            'components/auth/forgot-password-form.tsx',
            'pages/auth/reset-password.tsx',
            'pages/auth/confirm-password.tsx',
            'pages/auth/two-factor-challenge.tsx',
            'components/site/site-header.tsx',
        ] as $file
    ) {
        expect($filesystem->get(resource_path("js/{$file}")))
            ->toContain('variant="auth"');
    }
});

test('frontend components use semantic colors instead of palette-specific classes', function () {
    $filesystem = app(Filesystem::class);
    $source = collect($filesystem->allFiles(resource_path('js')))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['ts', 'tsx'], true))
        ->map(fn (SplFileInfo $file): string => $filesystem->get($file->getPathname()))
        ->implode("\n");

    preg_match_all(
        '/(?:bg|text|border|ring|decoration|divide)-(?:neutral|sky|emerald|rose|amber|zinc|slate|gray|stone|red|green|blue|yellow|orange|violet|purple)-[0-9]+/',
        $source,
        $matches,
    );

    expect($matches[0])->toBeEmpty();
});

test('search favorites settings and resources share the site page container', function () {
    $filesystem = app(Filesystem::class);

    $container = $filesystem->get(resource_path('js/components/site/site-page-container.tsx'));

    expect($container)
        ->toContain('max-w-7xl')
        ->toContain("density?: 'default' | 'compact'")
        ->toContain('gap-3 pt-4 pb-8 sm:gap-4 sm:pt-5 sm:pb-10')
        ->not->toContain("'gap-6 py-8 sm:py-10'");

    foreach ([
        'search.tsx',
        'users/show.tsx',
        'settings/index.tsx',
        'resources/index.tsx',
        'resources/show.tsx',
    ] as $page) {
        $source = $filesystem->get(resource_path("js/pages/{$page}"));

        expect($source)
            ->toContain("import { SitePageContainer } from '@/components/site/site-page-container';")
            ->toContain('<SitePageContainer');
    }

    expect($filesystem->get(resource_path('js/pages/resources/show.tsx')))
        ->toContain("import { Breadcrumbs } from '@/components/breadcrumbs';")
        ->toContain('<Breadcrumbs breadcrumbs={breadcrumbs} />')
        ->toContain("title: 'Games'")
        ->toContain('resource.categorySlug')
        // The hero stacks on mobile and becomes a row from sm up, where the cover
        // takes only the width it needs and the title block flexes into the rest —
        // stretching to the cover's height so the actions row sits at the bottom.
        // No backdrop image and no card around it, and no extra gutter of its own.
        ->toContain('flex flex-col gap-4 sm:flex-row sm:gap-6')
        // The column only reserves a box while the skeleton is up: once the image
        // lands, a short (very wide) cover must not leave a void under itself.
        ->toContain("'min-w-40 shrink-0 sm:max-w-[38%] sm:self-start'")
        ->toContain('!coverLoaded &&')
        ->toContain('min-h-48 sm:min-h-44 sm:w-[38%] lg:min-h-64')
        ->not->toContain('grid grid-cols-[minmax(0,42%)_minmax(0,1fr)]')
        ->not->toContain('View full size image for')
        ->not->toContain('bg-gradient-to-t')
        ->toContain('View full size cover for')
        ->toContain('fit="contain"')
        // The cover is sized by the image, not by a fixed 16:9 frame: a narrow
        // cover would otherwise sit between two empty bars. Height is capped so a
        // tall cover shrinks in width instead of growing past the hero, and it is
        // centred while the hero is stacked (mobile) and left-aligned beside the text.
        // The only 16:9 box is the skeleton that holds the slot until the image paints.
        ->toContain('mx-auto block h-auto')
        ->toContain('max-h-48 w-auto max-w-full')
        ->toContain('sm:mx-0 sm:max-h-44 lg:max-h-64')
        ->toContain('h-48 w-full animate-pulse rounded-md bg-muted')
        ->toContain('text-sm text-muted-foreground')
        ->toContain('FavoriteButton')
        ->toContain('showLabel')
        ->toContain('Favorite for update alerts.')
        ->toContain('mt-auto w-full max-w-full')
        ->toContain('flex flex-wrap items-center gap-1.5')
        ->toContain('w-full self-end text-left')
        ->toContain('md:ml-auto md:w-auto md:text-right')
        ->toContain('line-clamp-2')
        ->toContain('Released')
        ->toContain('Listed')
        // The old fixed cover box is gone; the hero stacks until sm and the cover
        // is sized by the image from there.
        ->not->toContain('h-[280px]')
        ->not->toContain('max-w-[498px]');

    expect($filesystem->get(resource_path('js/components/breadcrumbs.tsx')))
        // One row on mobile: the list never wraps, middle steps collapse into an
        // ellipsis, and a long page title truncates rather than pushing the row.
        ->toContain('flex-nowrap')
        ->toContain("isMiddle && 'hidden sm:inline-flex'")
        ->toContain("isLast && 'min-w-0'")
        ->toContain('sr-only')
        ->toContain('truncate');

    expect($filesystem->get(resource_path('js/components/site/favorite-button.tsx')))
        ->toContain('showLabel?: boolean')
        ->toContain("showLabel ? 'default' : size")
        ->toContain("showLabel ? 'Favorite' : null");

    expect($filesystem->get(resource_path('js/components/site/resource-tab-content.tsx')))
        ->not->toContain('resource-overview-heading')
        ->not->toContain('Overview')
        // Tags and description carry their own headings instead of one "About" block.
        ->not->toContain('resource-about-heading')
        ->not->toContain('About')
        ->toContain('id="resource-tags-heading"')
        ->toContain('aria-labelledby="resource-tags-heading"')
        ->toContain('id="resource-description-heading"')
        ->toContain('aria-labelledby="resource-description-heading"')
        ->toContain('Tags')
        ->toContain('Description')
        // Both sections sit on the page, not inside a card block.
        ->toMatch(
            '/<section\s+className="flex flex-col gap-3 sm:gap-4"\s+aria-labelledby="resource-description-heading"/',
        )
        ->toMatch(
            '/<section\s+className="flex flex-col gap-3 sm:gap-4"\s+aria-labelledby="resource-tags-heading"/',
        )
        // Description leads the panel; tags follow as secondary metadata.
        ->toMatch(
            '/aria-labelledby="resource-description-heading"[\s\S]*aria-labelledby="resource-tags-heading"/',
        )
        ->not->toContain('bg-muted/25')
        ->toContain('resource-downloads-heading')
        ->toContain('resource-screenshots-heading')
        ->toContain('Related games');

    expect($filesystem->get(resource_path('js/components/site/popular-resources.tsx')))
        ->toContain('<h2');

    expect($filesystem->get(resource_path('js/components/site/latest-resources.tsx')))
        ->toContain('nextPageHref?: string | null')
        ->toContain('Next page')
        // Cards stack one per row on phones.
        ->toContain('grid grid-cols-1')
        ->toContain('grid-cols-2')
        ->toContain('<ChevronRight data-icon="inline-end" />');

    expect($filesystem->get(resource_path('js/pages/welcome.tsx')))
        ->toContain('hasMoreResources: boolean')
        ->toContain('resourcesIndex({ query: { page: 2 } }).url');
});

test('the description language switcher is a dropdown that keeps every version mounted', function () {
    $source = app(Filesystem::class)->get(
        resource_path('js/components/site/resource-tab-content.tsx'),
    );

    expect($source)
        // A second tab row would compete with the page's own tabs, and a game
        // can carry more translations than a horizontal strip survives.
        ->not->toContain('TabsList')
        ->not->toContain('TabsTrigger')
        ->not->toContain('forceMount')
        ->toContain('<DropdownMenu')
        // A radio group so the menu announces the current language as checked.
        ->toContain('<DropdownMenuRadioGroup')
        ->toContain('<DropdownMenuRadioItem')
        // Trigger reads as a language control: glyph, current name, chevron.
        ->toContain('<Languages')
        ->toContain('aria-label={`Description language: ${activeVersion.name}`}')
        // No focus ring, matching the other dropdown triggers in the project.
        ->toContain('focus-visible:ring-0')
        // Inactive versions stay in the DOM so crawlers still see every translation.
        ->toContain("version.code !== activeVersion.code && 'hidden'")
        ->toContain('<RichHtml html={version.html} />');
});

test('detail page mutations keep the tab across the redirect back', function () {
    $filesystem = app(Filesystem::class);

    // Likes still redirect `back()` to the detail page. The tab rides in the query
    // string, which the Referer carries, so it survives on its own; preserving the
    // URL also keeps Inertia from rewriting the address bar mid-flight.
    expect($filesystem->get(resource_path('js/hooks/use-like.ts')))
        ->toContain('preserveUrl: true');

    // Favorites post in the background instead: there is no redirect to preserve a
    // URL across, and no Inertia visit means no progress bar on every click.
    expect($filesystem->get(resource_path('js/hooks/use-favorite.ts')))
        ->toContain('useHttp')
        ->not->toContain('router.post');
});

test('auth redirects carry the address bar instead of the stale inertia url', function () {
    $filesystem = app(Filesystem::class);

    // `usePage().url` knows neither the tab (written with the raw history API)
    // nor a fragment, so signing in from `?tab=downloads#cover` would land the
    // reader back on the default tab.
    expect($filesystem->get(resource_path('js/lib/current-url.ts')))
        ->toContain('window.location.hash');

    foreach (
        [
            'components/site/site-header.tsx',
            'hooks/use-favorite.ts',
            'hooks/use-like.ts',
        ] as $file
    ) {
        expect($filesystem->get(resource_path("js/{$file}")))
            ->toContain('currentUrl()')
            ->not->toContain('redirect: page.url');
    }
});

test('the active tab is read from the query string, not a fragment', function () {
    $source = app(Filesystem::class)->get(
        resource_path('js/lib/resource-tabs.ts'),
    );

    // A fragment is dropped by every redirect that lands back here — no
    // fragment in the Referer, none in an XHR response URL — which is why the
    // tab moved into `?tab=`. A fragment stays a position in the document.
    expect($source)
        ->toContain("params.get('tab')")
        ->toContain("url.searchParams.set('tab', tab)")
        ->toContain("url.searchParams.delete('tab')")
        ->toContain("url.hash = ''")
        ->toContain("router.on('navigate'");
});

test('switching tabs replaces the url instead of pushing a history entry', function () {
    $source = app(Filesystem::class)->get(
        resource_path('js/lib/resource-tabs.ts'),
    );

    // A tab is a view switch on a page the reader already has open. Pushing an
    // entry per tab would make Back walk back through every tab that was peeked
    // at — the hero download button alone switches one — instead of leaving the
    // page they arrived from.
    expect($source)
        ->toContain('window.history.replaceState(window.history.state')
        ->not->toContain('window.history.pushState');
});

test('the server rendered tab is the one hydration starts from', function () {
    $filesystem = app(Filesystem::class);

    // With SSR on, the first paint is the server's markup: if hydration started
    // from the default tab instead, a `?tab=` deep link would visibly repaint.
    // Asserted as tokens because the formatter is free to wrap the call.
    expect($filesystem->get(resource_path('js/pages/resources/show.tsx')))
        ->toContain('useResourceTab(initialTab)');

    expect($filesystem->get(resource_path('js/lib/resource-tabs.ts')))
        ->toContain('resourceLocationServerSnapshot(initialTab)')
        ->toContain('() => initialTab');
});

test('site empty states and download buttons use primary CTAs', function () {
    $filesystem = app(Filesystem::class);

    expect($filesystem->get(resource_path('js/components/site/site-empty-state.tsx')))
        ->toContain('border-dashed')
        ->toContain('bg-card/50');

    expect($filesystem->get(resource_path('js/components/site/resource-detail-styles.ts')))
        ->toContain('downloadButtonClassName')
        ->toContain('downloadHeroButtonClassName')
        ->toContain('releaseFooterClassName')
        ->toContain('releaseFooterInnerClassName')
        ->toContain('hover:bg-primary/90')
        ->toContain('h-9 border border-transparent bg-primary px-3.5 text-sm text-primary-foreground')
        ->toContain('dark:border-primary/40 dark:bg-primary/90')
        ->toContain('hover:bg-primary/10 hover:text-primary')
        ->not->toContain('hover:bg-foreground hover:text-background')
        ->not->toContain('bg-foreground px-4 text-background')
        ->not->toContain('downloadButtonPalettes');

    expect($filesystem->get(resource_path('js/components/site/resource-tab-content.tsx')))
        ->toContain('downloadButtonClassName')
        ->toContain('releaseFooterClassName')
        ->toContain('platformBadgeClassName')
        ->toContain('languageBadgeClassName')
        ->toContain('fileSizeBadgeClassName')
        ->toContain('SiteEmptyState')
        ->not->toContain('downloadButtonPalettes');

    expect($filesystem->get(resource_path('js/components/site/route-tabs.tsx')))
        ->toContain('border-b-2')
        ->toContain('transition-[color,border-color]')
        ->toContain('border-primary text-foreground')
        ->toContain('border-transparent text-muted-foreground')
        ->toContain('motion-reduce:transition-none')
        ->not->toContain('rounded-sm bg-accent')
        ->not->toContain('motion/react')
        ->not->toContain('motion.span');

    expect($filesystem->get(resource_path('js/components/site/resource-tab-content.tsx')))
        ->not->toContain('motion/react')
        ->not->toContain('motion.div');

    expect($filesystem->get(resource_path('js/lib/resource-tabs.ts')))
        ->toContain('export function nextResourceTabUrl')
        ->toContain("url.searchParams.delete('page')")
        ->toContain("url.searchParams.delete('focus')")
        ->toContain('window.history.replaceState(window.history.state')
        ->not->toContain('new PopStateEvent');

    expect($filesystem->get(resource_path('js/lib/resource-formatters.ts')))
        ->toContain("import { SITE_LOCALE } from '@/lib/datetime';")
        ->toContain('Intl.DateTimeFormat(SITE_LOCALE')
        ->toContain("month: 'short'")
        ->toContain("day: 'numeric'")
        ->toContain("year: 'numeric'")
        ->toContain('export function formatDate')
        ->toContain('export function formatReleaseDate')
        ->toContain('Jul 4, 2026');

    expect($filesystem->get(resource_path('js/lib/datetime.ts')))
        ->toContain("export const SITE_LOCALE = 'en-US';");

    expect($filesystem->get(resource_path('js/components/site/site-header.tsx')))
        ->toContain("aria-current={active ? 'page' : undefined}")
        ->toContain('showCloseButton={false}')
        ->toContain('justify-between gap-3')
        ->toContain('aria-label="Close menu"');

    expect($filesystem->get(resource_path('js/components/site/site-footer.tsx')))
        ->toContain('All rights reserved')
        ->toContain('footerLinks')
        ->toContain('aria-label="Footer"')
        ->not->toContain('taxonomyNav')
        ->not->toContain('resourcesGenre')
        ->not->toContain('<SiteLogo');

    expect($filesystem->get(resource_path('js/components/site/catalog-browse-links.tsx')))
        ->toContain('export function CatalogBrowseLinks')
        ->toContain('resourcesGenre.url')
        ->toContain('resourcesTag.url')
        ->toContain('resourcesTagsIndex');

    expect($filesystem->get(resource_path('js/components/site/site-header.tsx')))
        ->toContain('Tags')
        ->toContain('TaxonomyFlyout')
        // Genres/Languages are admin-managed taxonomy items now.
        ->toContain("item.type === 'taxonomy'")
        ->toContain('taxonomySources')
        ->toContain('resourcesGenre.url')
        ->toContain('resourcesLanguage.url')
        ->toContain('bg-[color-mix(in_oklch,var(--surface-raised)_88%,var(--background))]')
        ->not->toContain('backdrop-blur')
        ->not->toContain('staticGameCategories')
        ->not->toContain('GamesCategoryMenu')
        ->not->toContain('SiteTaxonomyNavDesktop')
        ->not->toContain('SiteTaxonomyNavMobile');

    expect($filesystem->get(resource_path('js/pages/resources/tags.tsx')))
        ->toContain('Game Tags')
        ->toContain('resourcesTag.url');
});

test('the user menu includes a profile entry to the user center', function () {
    $source = app(Filesystem::class)->get(resource_path('js/components/user-menu-content.tsx'));

    expect($source)
        ->toContain("import { favorites, show as userShow } from '@/routes/users'")
        ->toContain('href={userShow(user.slug)}')
        ->toContain('Profile')
        ->toContain('href={favorites(user.slug)}');
});

test('docs pages use the public site shell instead of the starter kit app layout', function () {
    $filesystem = app(Filesystem::class);
    $bootstrap = $filesystem->get(resource_path('js/app.tsx'));

    expect($bootstrap)
        ->toContain("name.startsWith('docs/')")
        ->toContain('return AuthModalLayout');

    foreach (['docs/index.tsx', 'docs/show.tsx'] as $page) {
        $source = $filesystem->get(resource_path("js/pages/{$page}"));

        expect($source)
            ->toContain("import { SiteLayout } from '@/layouts/site-layout';")
            ->toContain('<SiteLayout')
            ->not->toContain('AppLayout');
    }
});

test('search and favorite results use detailed card grids', function () {
    $filesystem = app(Filesystem::class);
    $searchResults = $filesystem->get(resource_path('js/components/site/search-results.tsx'));
    $favorites = $filesystem->get(resource_path('js/pages/users/show.tsx'));
    $resourceCard = $filesystem->get(resource_path('js/components/site/resource-card.tsx'));
    $catalog = $filesystem->get(resource_path('js/pages/resources/index.tsx'));

    expect($searchResults)
        // Cards stack one per row on phones.
        ->toContain('grid grid-cols-1')
        ->toContain('grid-cols-2')
        ->toContain('lg:grid-cols-3')
        ->toContain('xl:grid-cols-4')
        ->toContain('<DetailedResourceCard');

    expect($catalog)
        ->toContain('grid grid-cols-1 gap-3')
        ->toContain('sm:grid-cols-2')
        ->toContain('lg:grid-cols-3')
        ->toContain('xl:grid-cols-4');

    // Favorites reuse the catalogue card, plus the update and remove affordances.
    expect($favorites)
        ->toContain('<ResourceCard')
        ->toContain('grid grid-cols-1 gap-3 sm:grid-cols-2')
        ->toContain('lg:grid-cols-3')
        ->toContain('xl:grid-cols-4')
        ->toContain('highlightUpdate={')
        ->toContain('onRemove={');

    expect($resourceCard)
        // Category and platform icons share the left edge of the artwork.
        ->toContain('flex min-w-0 flex-wrap items-center gap-1')
        ->toContain('p-1.5 pr-9')
        // The remove control lives in the corner the platforms used to take.
        ->toContain('Remove from favorites?')
        ->toContain('hasDownloadUpdate')
        // 4:3 frame (the shape of most covers) with the whole artwork contained on
        // top of a blurred copy of itself.
        ->toContain('aspect-[4/3]')
        ->toContain('fit="contain"')
        ->toContain('ambient')
        ->toContain('ring-info/45')
        ->toContain('bg-info px-1.5 py-0.5')
        ->toContain('Updated')
        ->toContain('absolute top-1.5 right-1.5');
});

test('detailed resource cards avoid decorative hover motion and shadows', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/detailed-resource-card.tsx'));

    expect($source)
        ->not->toContain('hover:-translate-y')
        ->not->toContain('hover:shadow-')
        ->not->toContain('group-hover:scale-');
});

test('resource and detailed cards keep translucent thumbnail chips without backdrop blur', function () {
    $filesystem = app(Filesystem::class);
    $styles = $filesystem->get(resource_path('js/components/site/resource-card-styles.ts'));
    $resourceCard = $filesystem->get(resource_path('js/components/site/resource-card.tsx'));
    $detailedCard = $filesystem->get(resource_path('js/components/site/detailed-resource-card.tsx'));

    expect($styles)
        ->toContain('overlayChipClassName')
        ->toContain('resourceCardTitleClassName')
        ->toContain('bg-black/50')
        ->not->toContain('backdrop-blur')
        // The update cue rides inside the meta line, so it stays chromeless.
        ->toContain('resourceCardUpdateBadgeClassName')
        ->not->toContain('bg-info')
        ->toContain('font-semibold tracking-tight text-foreground');

    expect($resourceCard)
        ->toContain('resourceCardTitleClassName')
        ->toContain('@/components/site/resource-card-styles')
        ->toContain('overlayChipClassName')
        ->not->toContain('font-mono')
        ->not->toContain('text-foreground/85');

    expect($detailedCard)
        ->toContain('resourceCardTitleClassName')
        ->toContain('resourceCardSubtitleClassName')
        ->toContain('@/components/site/resource-card-styles')
        ->toContain('ResourceOverlayLanguageGroup')
        ->not->toContain('font-mono')
        ->not->toContain('bg-background/90 text-xs font-medium text-foreground ring-1 ring-border/80');
});

test('site pages share the reusable pagination component', function () {
    $filesystem = app(Filesystem::class);
    $sitePagination = $filesystem->get(resource_path('js/components/site/site-pagination.tsx'));

    expect($sitePagination)
        ->toContain('export type PaginatedData')
        ->toContain('export function SitePagination');

    foreach (
        [
            'components/site/favorites-pagination.tsx',
            'components/site/resource-pagination.tsx',
            'pages/search.tsx',
        ] as $file
    ) {
        $source = $filesystem->get(resource_path("js/{$file}"));

        expect($source)->toContain('<SitePagination');
    }
});

test('site pagination supports direct page jumps', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/site-pagination.tsx'));

    expect($source)
        ->toContain('aria-label="Jump to page"')
        ->toContain('aria-label="Go to page"')
        ->toContain('type="number"')
        ->toContain('requestedPage >= 1')
        ->toContain('requestedPage <= lastPage')
        ->toContain('router.visit(pageUrl(targetPage)')
        ->toContain('onFinish: () => setIsJumping(false)');
});

test('homepage popular section uses a ranked landscape strip', function () {
    $filesystem = app(Filesystem::class);
    $popular = $filesystem->get(resource_path('js/components/site/popular-resources.tsx'));
    $welcome = $filesystem->get(resource_path('js/pages/welcome.tsx'));

    expect($popular)
        ->toContain('export function PopularResources')
        // Scrolling stays native so phones keep the compositor-driven swipe; the only
        // addition is a 1:1 mouse drag for mice that cannot swipe.
        ->toContain('overflow-x-auto')
        ->toContain("event.pointerType !== 'mouse'")
        ->toContain('onDragStart={(event) => event.preventDefault()}')
        ->toContain('scrollBy({ left: direction * step, behavior: ')
        ->toContain('Scroll popular left')
        ->toContain('Scroll popular right')
        ->toContain('disabled={edges.start}')
        ->toContain('disabled={edges.end}')
        ->not->toContain('embla')
        ->not->toContain('snap-x')
        ->not->toContain('::-webkit-scrollbar')
        ->toContain('aspect-[16/10]')
        ->toContain('from-black/85')
        ->toContain('Rank')
        ->toContain('formatViews')
        ->toContain('resource.views + resource.downloads')
        ->toContain('Heat')
        ->not->toContain('Eye')
        ->toContain('bg-black/60 text-white ring-1 ring-white/15')
        ->not->toContain('backdrop-blur')
        ->not->toContain('useDragScroll')
        ->not->toContain('fit="contain"')
        ->not->toContain('grid grid-cols-2')
        ->not->toContain('divide-y');

    expect($welcome)
        ->toContain('<PopularResources')
        ->toContain('<LatestResources')
        ->not->toContain('CatalogBrowseLinks')
        ->not->toContain('HomeTaxonomyPanel');
});

test('rankings page is a card grid without blur', function () {
    $source = app(Filesystem::class)->get(resource_path('js/pages/rankings/index.tsx'));

    expect($source)
        ->toContain('font-heading text-2xl font-semibold tracking-tight')
        ->toContain('LazyThumbnail')
        ->toContain('priority={entry.rank <= 4}')
        ->toContain('bg-primary text-primary-foreground')
        ->toContain('bg-foreground/85 text-background')
        ->toContain('bg-warning/90 text-warning-foreground')
        ->toContain('bg-surface-inverse text-surface-inverse-foreground')
        ->toContain('absolute top-1.5 left-1.5')
        ->toContain('justify-between gap-2')
        ->toContain('text-[11px] font-semibold text-white tabular-nums')
        ->not->toContain('absolute top-1.5 right-1.5')
        ->not->toContain('bg-surface-inverse/80')
        ->not->toContain('bg-primary px-1.5 py-1')
        ->not->toContain('drop-shadow-sm')
        ->toContain('grid min-w-0 grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4')
        ->toContain('aspect-[16/10]')
        ->toContain('absolute inset-x-0 bottom-0')
        ->toContain('bg-gradient-to-t from-black/85 via-black/45 to-transparent')
        ->toContain('entry.thumbnail')
        ->toContain('entry.category')
        ->toContain('entry.views + entry.downloads')
        ->toContain('Heat')
        ->not->toContain('entry.screenshots')
        ->not->toContain('previewScreenshotCount')
        ->not->toContain('Eye')
        ->not->toContain('title="Views"')
        ->not->toContain('min-h-10')
        ->not->toContain('sm:flex-row sm:items-center')
        ->not->toContain('min-w-full')
        ->not->toContain('grid-cols-[7rem_minmax(0,1fr)_auto]')
        ->toContain('prefetch')
        ->not->toContain('w-16 sm:w-20')
        ->not->toContain('Download')
        ->not->toContain('bg-black/60')
        ->not->toContain('backdrop-blur');
});

test('page seo component renders canonical robots og and json-ld overrides', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/page-seo.tsx'));

    expect($source)
        ->toContain('export function PageSeo')
        ->toContain('head-key="canonical"')
        ->toContain('head-key="robots"')
        ->toContain('head-key="og:image"')
        ->toContain('head-key="json-ld"')
        ->toContain('application/ld+json')
        ->toContain('dangerouslySetInnerHTML')
        ->toContain('serializeJsonLd');
});

test('turnstile forms stay locked until the widget reports a token', function () {
    $filesystem = app(Filesystem::class);

    expect($filesystem->get(resource_path('js/components/turnstile-widget.tsx')))
        ->toContain('onTokenChange')
        ->toContain("'expired-callback'")
        ->toContain("'error-callback'")
        ->toContain("'timeout-callback'")
        ->toContain('Complete the security check to continue.')
        // A blocked or stalled challenges.cloudflare.com used to leave a blank
        // box with the submit locked forever, and a silent console error.
        ->toContain("script.addEventListener('error'")
        ->toContain('SCRIPT_TIMEOUT_MS')
        ->toContain('Security check could not load')
        ->toContain('Retry')
        // Turnstile injects its own cf-turnstile-response input; a second one from
        // us could submit an empty or stale token.
        ->not->toContain('name="cf-turnstile-response"');

    expect($filesystem->get(resource_path('js/hooks/use-turnstile-gate.ts')))
        ->toContain('export function useTurnstileGate')
        ->toContain('submitDisabled')
        ->toContain('onBefore');

    foreach (
        [
            'components/auth/login-form.tsx',
            'components/auth/register-form.tsx',
            'components/auth/forgot-password-form.tsx',
        ] as $file
    ) {
        $source = $filesystem->get(resource_path("js/{$file}"));

        expect($source)
            ->toContain('useTurnstileGate')
            ->toContain('turnstileGate.submitDisabled')
            ->toContain('onTokenChange={')
            ->toContain('onBefore={turnstileGate.onBefore}');
    }

    // Download continue always POSTs (to record counts); Turnstile is optional.
    expect($filesystem->get(resource_path('js/pages/download-links/show.tsx')))
        ->toContain('useTurnstileGate')
        ->toContain('continueMethod.url(link.id)')
        ->toContain('showTurnstile ? turnstileGate.onBefore : undefined')
        ->toContain('turnstileGate.submitDisabled');
});

test('resource card thumbnails fade in with lazy loading', function () {
    $filesystem = app(Filesystem::class);
    $thumbnail = $filesystem->get(resource_path('js/components/site/lazy-thumbnail.tsx'));
    $imageLoadState = $filesystem->get(resource_path('js/hooks/use-image-load-state.ts'));
    $card = $filesystem->get(resource_path('js/components/site/resource-card.tsx'));
    $resourceShow = $filesystem->get(resource_path('js/pages/resources/show.tsx'));
    $resourceTabContent = $filesystem->get(resource_path('js/components/site/resource-tab-content.tsx'));

    expect($thumbnail)
        ->toContain('export function LazyThumbnail')
        ->toContain("loading={priority ? 'eager' : 'lazy'}")
        ->toContain("decoding={priority ? 'sync' : 'async'}")
        ->toContain('animate-pulse bg-muted')
        ->toContain("loaded ? 'opacity-100' : 'opacity-0'")
        ->toContain('useImageLoadState(src, fallbackSrc)')
        ->toContain('ref={imageRef}')
        ->toContain('onLoad={markLoaded}')
        ->toContain('onError={markError}')
        ->not->toContain('useEffect');

    expect($imageLoadState)
        ->toContain('export function useImageLoadState')
        ->toContain('const [loadedSrc')
        ->toContain('node?.complete')
        ->toContain('loaded: loadedSrc === displaySrc')
        ->not->toContain('useEffect');

    expect($card)
        ->toContain('LazyThumbnail')
        ->toContain('priority={priority}');

    expect($resourceShow)
        ->toContain('useImageLoadState(src, fallbackSrc)')
        ->toContain('ref={imageRef}')
        ->toContain('onLoad={markLoaded}')
        ->toContain('onError={markError}');

    expect($resourceTabContent)
        ->toContain('useImageLoadState(src)')
        ->toContain('ref={imageRef}')
        ->toContain('onLoad={markLoaded}')
        ->toContain('onError={markLoaded}');
});

test('resource catalog keeps seo metadata out of the visible interface', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/pages/resources/index.tsx'));
    $app = $filesystem->get(resource_path('js/app.tsx'));

    expect($source)
        ->toContain('<PageSeo seo={pageSeo}')
        ->not->toContain('catalogHeading')
        ->not->toContain('Hentai Games and Eroge Downloads')
        ->not->toContain('Use the search field to find a title')
        ->not->toContain('screenshots and downloads tabs');

    expect($app)
        ->toContain('resolvePageTitleSuffix')
        ->toContain('`${title} | ${pageTitleSuffix}`')
        ->toContain('`${title} | ${siteTitle}`')
        ->not->toContain('`${title} - ${siteTitle}`');
});

test('site pagination uses a compact page window instead of listing every page', function () {
    $filesystem = app(Filesystem::class);
    $pagination = $filesystem->get(resource_path('js/components/site/site-pagination.tsx'));
    $range = $filesystem->get(resource_path('js/lib/pagination-range.ts'));

    expect($pagination)
        ->toContain("import { buildPaginationRange } from '@/lib/pagination-range'")
        ->toContain('buildPaginationRange(')
        ->not->toContain('pagination.links.filter');

    expect($range)
        ->toContain('export function buildPaginationRange')
        ->toContain('showLeftEllipsis')
        ->toContain('showRightEllipsis')
        ->toContain("'ellipsis'");
});

test('notification timestamps share one right column', function () {
    $source = app(Filesystem::class)->get(resource_path('js/pages/notifications/index.tsx'));

    expect($source)
        ->toContain('shrink-0 self-center text-right text-xs leading-4 text-muted-foreground tabular-nums')
        ->toContain('inline-flex size-4 shrink-0 items-center justify-center text-muted-foreground')
        ->not->toContain('items-baseline justify-between');
});

test('download release items use the compact responsive layout', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/resource-tab-content.tsx'));
    $styles = $filesystem->get(resource_path('js/components/site/resource-detail-styles.ts'));

    expect($source)
        ->toContain('flex flex-col gap-2.5')
        ->toContain('releaseFooterClassName')
        ->toContain('releaseFooterInnerClassName')
        ->toContain('size="sm"')
        ->toContain('downloadButtonClassName')
        ->toContain('aria-label="Package actions"')
        // The package bar carries no like toggle.
        ->not->toContain('LikeButton')
        ->toContain('Download')
        ->toContain('release.contributor')
        ->toContain('UserAvatar')
        ->toContain('Contributed by')
        ->toContain('release.contributor.name')
        // The contributor link wraps the avatar alone: the name and the package
        // dates are labels, not a second way to leave the page.
        ->toContain("'shrink-0 rounded-full'")
        ->not->toContain('inline-flex min-w-0 flex-1 items-center gap-2')
        ->toContain('release.downloadsUpdatedAt')
        ->toContain('showReleaseUpdate')
        ->not->toContain('hasLaterDownloadUpdate')
        // One date per bar: the update when the package has one, otherwise when
        // it was listed. The round trip through "both dates on one line" and
        // "update only" landed here — the update wins, the list date is the
        // fallback, and neither one is a chip inside the actions group.
        ->toContain('const packageUpdated')
        ->toContain('const packageListed')
        ->toContain('packageUpdated ?? packageListed')
        ->toContain('Package downloads updated')
        ->toContain('Package listed')
        ->not->toContain('const packageDates')
        // The update keeps the accent the old chip used, so it still stands out.
        ->toContain('shrink-0 font-medium text-info')
        ->not->toContain('DownloadUpdateChip')
        // Each bar counts its own package, not the game total.
        ->toContain('count={release.downloads}')
        // One leading "v": version input that already says "Ver1.02" must not
        // come out as "vVer1.02", and a package row keeps the whole label.
        ->toContain('formatVersion(')
        ->not->toContain('v{release.version}')
        ->toContain('showDownloadCount')
        ->toContain('hidden sm:inline-flex')
        ->toContain('flex shrink-0 items-center gap-2 sm:hidden')
        ->toContain('${formatted} downloads')
        ->toContain('releaseDescriptionNeedsToggle')
        ->toContain("overflow: 'hidden'")
        ->toContain('RELEASE_DESCRIPTION_COLLAPSED_MAX_PX')
        // Each mirror is named after its own domain, so several links on one
        // release can be told apart.
        ->toContain('link.label')
        ->toContain('Download from ${name}')
        // No per-mirror icon is fetched; the button is the download arrow plus
        // the name.
        ->not->toContain('link.faviconUrl')
        ->not->toContain('favicons')
        ->not->toContain('useLayoutEffect')
        ->not->toContain('ResizeObserver')
        ->not->toContain('link.contributor')
        ->not->toContain('p-3 sm:p-4')
        ->not->toContain('border-b border-border bg-muted/50 px-4 py-3.5');

    expect($styles)
        ->toContain('border-t border-border/70 bg-muted/35')
        ->toContain('sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5')
        ->toContain("'h-8 min-w-0 gap-1.5")
        ->toContain('bg-primary')
        ->toContain('hover:bg-primary/90')
        ->not->toContain('animate-heartbeat');
});

test('button variants use deliberate dark mode surfaces and borders', function () {
    $filesystem = app(Filesystem::class);
    $button = $filesystem->get(resource_path('js/components/ui/button.tsx'));
    $resourceStyles = $filesystem->get(resource_path('js/components/site/resource-detail-styles.ts'));
    $favoriteButton = $filesystem->get(resource_path('js/components/site/favorite-button.tsx'));
    $resourceCard = $filesystem->get(resource_path('js/components/site/detailed-resource-card.tsx'));

    expect($button)
        ->toContain('dark:border-foreground/15 dark:bg-surface-raised')
        ->toContain('dark:bg-surface-strong')
        ->toContain('dark:border-destructive/25 dark:bg-destructive/15')
        ->toContain('bg-auth text-auth-foreground');

    expect($resourceStyles)
        ->toContain('downloadButtonClassName')
        ->toContain('downloadHeroButtonClassName')
        ->toContain('releaseFooterClassName')
        ->toContain('h-9 border border-transparent bg-primary px-3.5 text-sm text-primary-foreground')
        ->toContain('hover:bg-primary/90')
        ->toContain('dark:border-primary/40 dark:bg-primary/90')
        ->not->toContain('border-0 bg-foreground')
        ->not->toContain('bg-foreground px-4 text-background')
        ->not->toContain('bg-auth text-auth-foreground')
        ->not->toContain('downloadButtonPalettes');

    expect($favoriteButton)
        ->toContain('heroFavoriteActiveClassName')
        ->toContain('heroActionIdleClassName');

    expect($resourceStyles)
        ->toContain('dark:bg-favorite/22')
        ->toContain('dark:bg-white/10')
        ->toContain('border-0');

    expect($resourceCard)
        ->toContain('Remove favorite')
        ->toContain('overlayChipClassName')
        ->toContain('sm:group-hover:opacity-100')
        ->toContain('<Spinner');
});

test('social login buttons include telegram', function () {
    $filesystem = app(Filesystem::class);
    $buttons = $filesystem->get(resource_path('js/components/auth/social-login-buttons.tsx'));
    $providers = $filesystem->get(resource_path('js/types/auth.ts'));

    expect($buttons)
        ->toContain("telegram: 'Continue with Telegram'")
        ->toContain("provider === 'telegram'")
        ->toContain('data-test={`social-login-${provider}`}');

    expect($providers)->toContain("'google' | 'discord' | 'telegram'");
});

test('tab switches never add a history entry', function () {
    $tabs = app(Filesystem::class)->get(resource_path('js/lib/resource-tabs.ts'));
    $routeTabs = app(Filesystem::class)->get(resource_path('js/components/site/route-tabs.tsx'));
    $entry = app(Filesystem::class)->get(resource_path('js/app.tsx'));

    // A click that lands before hydration must not navigate: the guard replaces the
    // URL instead, so Back leaves the page rather than stepping to the details tab.
    expect($tabs)
        ->toContain('installResourceTabClickGuard')
        ->toContain('RESOURCE_TAB_ATTRIBUTE')
        ->toContain("window.history.replaceState(window.history.state, '', next)");

    expect($routeTabs)
        ->toContain('RESOURCE_TAB_ATTRIBUTE')
        ->toContain('onSelect');

    // Installed at module scope, i.e. before React hydrates.
    expect($entry)->toContain('installResourceTabClickGuard();');

    // Back/forward rewrites the URL before the router swaps pages: the tab must hold
    // its last value, otherwise the details panel repaints for a frame on the way out.
    expect($tabs)
        ->toContain('locationRef')
        ->toContain('path !== cached.path')
        ->toContain('return cached.tab;');
});
