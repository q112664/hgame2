<?php

use Illuminate\Filesystem\Filesystem;

test('resource filter controls keep the inline dropdowns, tag modal, and standalone sort', function () {
    $filesystem = app(Filesystem::class);
    $controls = $filesystem->get(resource_path('js/components/site/resource-filter-controls.tsx'));
    $index = $filesystem->get(resource_path('js/pages/resources/index.tsx'));

    expect($controls)
        ->toContain("q: ''")
        ->toContain('query.q = filters.q.trim()')
        ->toContain('export function catalogUrl')
        ->toContain('resourcesGenre.url')
        ->toContain('export function countActiveFilters')
        ->toContain('export function FilterMenu')
        ->toContain('export function TagFilterDialog')
        ->toContain('export function SortFieldMenu')
        ->toContain('export function SortDirectionButton')
        ->toContain('export function sortFieldOf')
        ->toContain('export function sortSelection')
        ->toContain('aria-label="Sort resources"')
        ->toContain('aria-label={`Sort direction: ')
        // The direction toggle carries an abbreviated label and no focus ring.
        ->toContain("ascending ? 'Asc' : 'Desc'")
        ->toContain('export function defaultDirectionForField')
        ->toContain('focus-visible:ring-0 focus-visible:outline-none')
        ->toContain('aria-label={label}')
        ->not->toContain('tagFilterOpen');

    // Category / platform / language stay inline dropdowns; only tags open a
    // modal, because that taxonomy is far too long for a menu.
    expect($controls)
        ->toContain('Filter by tags')
        ->toContain('aria-pressed={checked}')
        ->toContain('<DialogContent')
        ->toContain('<DialogTrigger asChild>')
        ->not->toContain('ActiveFilterChips')
        ->not->toContain('ResourceFilterPanel')
        ->not->toContain('SlidersHorizontal');

    // Sorting is decoupled from filtering: field and direction only reorder.
    expect($controls)
        ->toContain(
            "type SortOption = 'latest' | 'oldest' | 'updated' | 'title' | 'views'",
        )
        ->toContain("label: 'Published date'")
        ->toContain("label: 'Last updated'")
        ->toContain("label: 'Views'")
        ->not->toContain('Newest listed')
        ->not->toContain('Oldest listed')
        ->not->toContain('Most viewed');

    expect($index)
        ->toContain('id="resource-search"')
        ->toContain('Search titles, tags, developers…')
        ->toContain('<FilterMenu')
        ->toContain('<TagFilterDialog')
        ->toContain('<SortFieldMenu')
        ->toContain('<SortDirectionButton')
        ->not->toContain('<ResourceFilterPanel')
        // Selected filters live in the controls themselves, not in a chip row.
        ->not->toContain('ActiveFilterChips')
        // Except tags, which echo as removable badges under the controls.
        ->toContain('selectedTagNames')
        ->toContain('aria-label={`Remove ${tag.name}`}')
        ->not->toContain('<SortMenu')
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
        ->toContain("dateField === 'downloadsUpdatedAt'")
        ->toContain('showsUpdateDate')
        // The update cue sits on the thumbnail, next to the version chip.
        ->toContain('{formatCompactDate(updatedAt)}')
        ->toContain('Downloads last updated ${formatDate(updatedAt)}')
        ->toContain('<RefreshCw')
        ->toContain("target={openInNewWindow ? '_blank' : undefined}");

    expect($filesystem->get(resource_path('js/lib/resource-formatters.ts')))
        // Thumbnail chips use a numeric date with the full year: 9/8/2026.
        ->toContain('export function formatCompactDate')
        ->toContain("year: 'numeric'");
});
