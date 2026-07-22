<?php

use Illuminate\Filesystem\Filesystem;

test('resource filter panel has a keyword search above label-free menus', function () {
    $filesystem = app(Filesystem::class);
    $controls = $filesystem->get(resource_path('js/components/site/resource-filter-controls.tsx'));
    $index = $filesystem->get(resource_path('js/pages/resources/index.tsx'));

    expect($controls)
        ->toContain("q: ''")
        ->toContain('query.q = filters.q.trim()')
        ->toContain('aria-label={label}')
        ->not->toContain('tracking-wide text-muted-foreground uppercase');

    expect($index)
        ->toContain('id="resource-search"')
        ->toContain('Search titles, tags, developers…')
        ->toContain('<TagFilterDialog')
        ->not->toContain('import { Label } from \'@/components/ui/label\';');
});
