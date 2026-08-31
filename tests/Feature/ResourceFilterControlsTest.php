<?php

use Illuminate\Filesystem\Filesystem;

test('resource filter panel has a keyword search above label-free menus', function () {
    $filesystem = app(Filesystem::class);
    $controls = $filesystem->get(resource_path('js/components/site/resource-filter-controls.tsx'));
    $index = $filesystem->get(resource_path('js/pages/resources/index.tsx'));

    expect($controls)
        ->toContain("q: ''")
        ->toContain('query.q = filters.q.trim()')
        ->toContain('export function catalogUrl')
        ->toContain('resourcesGenre.url')
        ->toContain('aria-label={label}')
        ->not->toContain('tracking-wide text-muted-foreground uppercase');

    expect($index)
        ->toContain('id="resource-search"')
        ->toContain('Search titles, tags, developers…')
        ->toContain('<TagFilterDialog')
        ->toContain('<h1')
        ->toContain('resource-results-heading')
        ->toContain('function OpenResourcesInNewWindowToggle')
        ->toContain('openInNewWindow={openInNewWindow}')
        ->toContain('useOpenResourcesInNewWindow')
        ->toContain('<Checkbox')
        ->toContain('New tab')
        ->not->toContain('<ExternalLink')
        ->not->toContain('New window');

    expect($filesystem->get(resource_path('js/hooks/use-open-resources-in-new-window.ts')))
        ->toContain("export const OPEN_RESOURCES_IN_NEW_WINDOW_KEY = 'resources.openInNewWindow'")
        ->toContain('localStorage.setItem')
        ->toContain('useSyncExternalStore');

    expect($filesystem->get(resource_path('js/components/site/resource-card.tsx')))
        ->toContain('openInNewWindow = false')
        ->toContain("target={openInNewWindow ? '_blank' : undefined}");
});
