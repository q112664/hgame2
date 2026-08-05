<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

test('the shared theme defines light and dark semantic tokens', function () {
    $filesystem = app(Filesystem::class);
    $stylesheet = $filesystem->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain(':root')
        ->toContain('.dark')
        ->toContain('bIkeymG');

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

test('warm red theme uses oklch tokens and default radius', function () {
    $stylesheet = app(Filesystem::class)->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('Fakku-inspired warm red')
        ->toContain('--background: oklch(0.97 0 0);')
        ->toContain('--card: oklch(1 0 0);')
        ->toContain('--primary: oklch(0.58 0.19 25);')
        ->toContain('--auth: oklch(0.58 0.19 25);')
        ->toContain('--accent: oklch(0.96 0.015 25);')
        ->toContain('--radius: 0.625rem;')
        ->toContain('--background: oklch(0.13 0 0);')
        ->toContain('--primary: oklch(0.66 0.18 25);')
        ->toContain('--auth: oklch(0.66 0.18 25);')
        ->toContain('--accent: oklch(0.28 0.04 25);')
        ->toContain("--font-sans: 'Inter Variable', sans-serif;")
        ->toContain('--radius-sm: calc(var(--radius) - 4px);')
        ->not->toContain('#3498db')
        ->not->toContain('#3a4d73')
        ->not->toContain('#f4f5f7');
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
        'favorites.tsx',
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
        ->toContain("title: 'Resources'")
        ->toContain('flex flex-col md:flex-row')
        ->toContain('md:aspect-auto md:h-[280px] md:w-auto md:max-w-[498px]')
        ->toContain('text-sm text-muted-foreground')
        ->toContain('FavoriteButton')
        ->not->toContain('sm:flex-row')
        ->not->toContain('sm:h-[280px]');
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
        ->toContain('bg-primary px-4 text-primary-foreground')
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
        ->toContain('dateBadgeClassName')
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

    expect($filesystem->get(resource_path('js/lib/resource-formatters.ts')))
        ->toContain("import { SITE_LOCALE } from '@/lib/datetime';")
        ->toContain('Intl.DateTimeFormat(SITE_LOCALE')
        ->toContain("month: 'short'")
        ->toContain('export function formatReleaseDate')
        ->toContain('`${month}/${day}/${year}`');

    expect($filesystem->get(resource_path('js/lib/datetime.ts')))
        ->toContain("export const SITE_LOCALE = 'en-US';");

    expect($filesystem->get(resource_path('js/components/site/site-header.tsx')))
        ->toContain("aria-current={active ? 'page' : undefined}")
        ->toContain('showCloseButton={false}')
        ->toContain('justify-between gap-3')
        ->toContain('aria-label="Close menu"');

    expect($filesystem->get(resource_path('js/components/site/home-hero.tsx')))
        ->toContain('hero.title')
        ->toContain('hero.browseLabel')
        ->toContain('bg-auth/22')
        ->toContain('backdrop-blur-sm');

    expect($filesystem->get(resource_path('js/components/site/site-footer.tsx')))
        ->toContain('All rights reserved')
        ->not->toContain('<SiteLogo');
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
    $favorites = $filesystem->get(resource_path('js/pages/favorites.tsx'));
    $favoriteCard = $filesystem->get(resource_path('js/components/site/favorite-resource-card.tsx'));

    expect($searchResults)
        ->toContain('sm:grid-cols-2')
        ->toContain('lg:grid-cols-3')
        ->toContain('xl:grid-cols-4')
        ->toContain('<DetailedResourceCard');

    expect($favorites)
        ->toContain('md:grid-cols-2')
        ->toContain('<FavoriteResourceCard')
        ->not->toContain('lg:grid-cols-3')
        ->not->toContain('xl:grid-cols-4');

    expect($favoriteCard)
        ->toContain('flex h-full min-h-0')
        ->toContain('aspect-[16/10] w-[42%]')
        ->toContain('Remove favorite');
});

test('detailed resource cards avoid decorative hover motion and shadows', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/detailed-resource-card.tsx'));

    expect($source)
        ->not->toContain('hover:-translate-y')
        ->not->toContain('hover:shadow-')
        ->not->toContain('group-hover:scale-');
});

test('resource and detailed cards share frosted thumbnail overlay chips', function () {
    $filesystem = app(Filesystem::class);
    $styles = $filesystem->get(resource_path('js/components/site/resource-card-styles.ts'));
    $resourceCard = $filesystem->get(resource_path('js/components/site/resource-card.tsx'));
    $detailedCard = $filesystem->get(resource_path('js/components/site/detailed-resource-card.tsx'));

    expect($styles)
        ->toContain('overlayChipClassName')
        ->toContain('resourceCardTitleClassName')
        ->toContain('bg-black/40')
        ->toContain('backdrop-blur-[2px]')
        ->toContain('font-semibold tracking-tight text-foreground');

    expect($resourceCard)
        ->toContain('resourceCardTitleClassName')
        ->toContain('@/components/site/resource-card-styles')
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
        ->toContain('overflow-x-auto')
        ->toContain('scrollBy')
        ->toContain('aspect-[16/10]')
        ->toContain('from-black/85')
        ->toContain('Rank')
        ->toContain('formatViews')
        ->not->toContain('useDragScroll')
        ->not->toContain('cursor-grab')
        ->not->toContain('fit="contain"')
        ->not->toContain('grid grid-cols-2')
        ->not->toContain('divide-y');

    expect($welcome)
        ->toContain('<PopularResources')
        ->toContain('<LatestResources');
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
        ->toContain('Complete the security check to continue.');

    expect($filesystem->get(resource_path('js/hooks/use-turnstile-gate.ts')))
        ->toContain('export function useTurnstileGate')
        ->toContain('submitDisabled')
        ->toContain('onBefore');

    foreach (
        [
            'components/auth/login-form.tsx',
            'components/auth/register-form.tsx',
            'components/auth/forgot-password-form.tsx',
            'pages/download-links/show.tsx',
        ] as $file
    ) {
        $source = $filesystem->get(resource_path("js/{$file}"));

        expect($source)
            ->toContain('useTurnstileGate')
            ->toContain('turnstileGate.submitDisabled')
            ->toContain('onTokenChange={')
            ->toContain('onBefore={turnstileGate.onBefore}');
    }
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
        ->toContain('useImageLoadState(src)')
        ->toContain('ref={imageRef}')
        ->toContain('onLoad={markLoaded}')
        ->toContain('onError={markLoaded}')
        ->not->toContain('useEffect');

    expect($imageLoadState)
        ->toContain('export function useImageLoadState')
        ->toContain('const [loadedSrc')
        ->toContain('node?.complete')
        ->toContain('loaded: loadedSrc === src')
        ->not->toContain('useEffect');

    expect($card)
        ->toContain('LazyThumbnail')
        ->toContain('priority={priority}');

    foreach ([$resourceShow, $resourceTabContent] as $source) {
        expect($source)
            ->toContain('useImageLoadState(src)')
            ->toContain('ref={imageRef}')
            ->toContain('onLoad={markLoaded}')
            ->toContain('onError={markLoaded}');
    }
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
        ->toContain('`${title} | ${pageTitleSuffix}`');
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

test('download release items use the compact responsive layout', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/resource-tab-content.tsx'));
    $styles = $filesystem->get(resource_path('js/components/site/resource-detail-styles.ts'));

    expect($source)
        ->toContain('flex flex-col gap-2.5')
        ->toContain('releaseFooterClassName')
        ->toContain('releaseFooterInnerClassName')
        ->toContain('flex min-w-0 flex-wrap items-center gap-1.5')
        ->toContain('size="sm"')
        ->toContain('downloadButtonClassName')
        ->toContain('aria-label="Download links"')
        ->toContain('CloudDownload')
        ->toContain('Download')
        ->not->toContain('link.label')
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
        ->toContain('border border-transparent bg-primary px-4 text-primary-foreground')
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
