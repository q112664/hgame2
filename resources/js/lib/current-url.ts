/**
 * The address bar as a local URL, fragment included.
 *
 * Inertia's `usePage().url` never carries the fragment — the tab is written with
 * raw `history.pushState` and a fragment is never sent to the server — so using
 * it as an auth redirect silently drops deep-link state such as `#downloads`
 * and the user lands back on the default tab.
 */
export function currentUrl(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return `${window.location.pathname}${window.location.search}${window.location.hash}`;
}
