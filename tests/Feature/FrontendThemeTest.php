<?php

use Illuminate\Filesystem\Filesystem;

test('the shared theme defines light and dark semantic tokens', function () {
    $filesystem = app(Filesystem::class);
    $stylesheet = $filesystem->get(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain(':root')
        ->toContain('.dark');

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
