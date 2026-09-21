/**
 * The address bar as a local URL, query and comment anchor included.
 *
 * Inertia's `usePage().url` cannot stand in for this: the active tab is written
 * with the raw history API, which Inertia does not observe, and the page URL
 * never carries the fragment either. Using it as an auth redirect would drop
 * `?tab=downloads` and land the reader back on the default tab, or drop
 * `#comment-9` and leave them at the top of the reviews.
 */
export function currentUrl(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return `${window.location.pathname}${window.location.search}${window.location.hash}`;
}
