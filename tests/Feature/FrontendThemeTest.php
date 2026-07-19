<?php

use Illuminate\Filesystem\Filesystem;

test('the shared theme defines light and dark semantic tokens', function () {
    $filesystem = app(Filesystem::class);
    $stylesheet = $filesystem->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain(':root')
        ->toContain('.dark')
        ->toContain('Ink Archive');

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

test('ink archive theme uses cool paper surfaces and ink primary', function () {
    $stylesheet = app(Filesystem::class)->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('--background: #f4f5f7;')
        ->toContain('--primary: #3a4d73;')
        ->toContain('--radius: 0.375rem;')
        ->toContain('--background: #0e1014;')
        ->toContain('--primary: #9aafd4;')
        ->not->toContain('#c84b5a')
        ->not->toContain('#ef7a86');
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

test('search favorites and settings share the site page container', function () {
    $filesystem = app(Filesystem::class);

    $container = $filesystem->get(resource_path('js/components/site/site-page-container.tsx'));

    expect($container)->toContain('max-w-7xl');

    foreach (['search.tsx', 'favorites.tsx', 'settings/index.tsx'] as $page) {
        $source = $filesystem->get(resource_path("js/pages/{$page}"));

        expect($source)
            ->toContain("import { SitePageContainer } from '@/components/site/site-page-container';")
            ->toContain('<SitePageContainer');
    }
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
        ->toContain('bg-black/40')
        ->toContain('backdrop-blur-[2px]');

    expect($resourceCard)
        ->toContain("import { overlayChipClassName } from '@/components/site/resource-card-styles';")
        ->not->toContain('font-mono');

    expect($detailedCard)
        ->toContain("import { overlayChipClassName } from '@/components/site/resource-card-styles';")
        ->toContain('abbreviateLanguage')
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

test('download release items use the compact responsive layout', function () {
    $filesystem = app(Filesystem::class);
    $source = $filesystem->get(resource_path('js/components/site/resource-tab-content.tsx'));

    expect($source)
        ->toContain('p-3 sm:p-4')
        ->toContain('lg:flex-row')
        ->toContain('lg:max-w-[42%]')
        ->toContain('size="sm"')
        ->not->toContain('border-b border-border bg-muted/50 px-4 py-3.5');
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
        ->toContain('dark:border-destructive/25 dark:bg-destructive/15');

    expect($resourceStyles)
        ->toContain('dark:bg-primary/15')
        ->toContain('dark:bg-info/15')
        ->toContain('dark:bg-success/15');

    expect($favoriteButton)
        ->toContain('dark:border-primary/30 dark:bg-primary/15')
        ->toContain('dark:border-foreground/15 dark:bg-surface-raised');

    expect($resourceCard)
        ->toContain('Remove favorite')
        ->toContain('overlayChipClassName')
        ->toContain('sm:group-hover:opacity-100')
        ->toContain('<Spinner');
});
