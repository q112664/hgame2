import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { show as resourceDetails } from '@/routes/resources';

export type ResourceTab = 'details' | 'downloads' | 'screenshots' | 'comments';

const RESOURCE_TAB_LOCATION_EVENT = 'resource-tab-location';

export function parseResourceTab(hash: string, search = ''): ResourceTab {
    const value = hash.startsWith('#') ? hash.slice(1) : hash;
    let tab: ResourceTab = 'details';

    if (
        value === 'downloads' ||
        value === 'screenshots' ||
        value === 'comments'
    ) {
        tab = value;
    } else if (value.startsWith('comment-')) {
        tab = 'comments';
    } else {
        const params = new URLSearchParams(
            search.startsWith('?') ? search.slice(1) : search,
        );

        if (params.has('focus')) {
            tab = 'comments';
        } else {
            const page = Number(params.get('page') ?? '');

            if (Number.isInteger(page) && page > 1) {
                tab = 'comments';
            }
        }
    }

    return tab;
}

export function nextResourceTabUrl(href: string, tab: ResourceTab): string {
    const url = new URL(href, 'http://localhost');

    url.hash = tab === 'details' ? '' : tab;

    if (tab !== 'comments') {
        url.searchParams.delete('page');
        url.searchParams.delete('focus');
    }

    return `${url.pathname}${url.search}${url.hash}`;
}

export function resourceTabHref(resourceId: string, tab: ResourceTab): string {
    const url = resourceDetails.url(resourceId);

    return tab === 'details' ? url : `${url}#${tab}`;
}

export function commentsPageUrl(resourceId: string, page: number): string {
    const url = resourceDetails.url(resourceId, {
        query: page > 1 ? { page } : {},
    });

    return `${url}#comments`;
}

const isServer = typeof window === 'undefined';

/**
 * The active tab is remembered here instead of being re-derived from the URL on
 * every render, because Inertia rewrites the address bar from the redirect target
 * on any visit that redirects back — favourites, likes, comments, sign-in — and a
 * browser never sends the fragment in the Referer. Without that memory
 * `#downloads` would silently disappear and the page would snap back to details.
 *
 * The URL stays the mirror: it is adopted whenever the browser itself moved, and
 * repaired whenever a visit dropped the fragment.
 */
let activeTab: ResourceTab = isServer
    ? 'details'
    : parseResourceTab(window.location.hash, window.location.search);
let pagePath = isServer ? '' : window.location.pathname;

/** The URL wins whenever the browser (back/forward, edited hash) moved. */
function adoptUrl(): void {
    activeTab = parseResourceTab(window.location.hash, window.location.search);
    pagePath = window.location.pathname;
}

function subscribeResourceLocation(onChange: () => void): () => void {
    const handleBrowserNavigation = () => {
        adoptUrl();
        onChange();
    };

    const handleVisit = () => {
        if (window.location.pathname !== pagePath) {
            // Another page: the URL is the only source of truth.
            handleBrowserNavigation();

            return;
        }

        if (activeTab !== 'details' && window.location.hash === '') {
            // The visit landed back here without the tab fragment, so put it
            // back rather than letting the URL and the tab disagree.
            window.history.replaceState(
                window.history.state,
                '',
                nextResourceTabUrl(window.location.href, activeTab),
            );
        }

        onChange();
    };

    window.addEventListener('hashchange', handleBrowserNavigation);
    window.addEventListener('popstate', handleBrowserNavigation);
    window.addEventListener(RESOURCE_TAB_LOCATION_EVENT, onChange);

    const stopWatchingVisits = router.on('navigate', handleVisit);

    return () => {
        window.removeEventListener('hashchange', handleBrowserNavigation);
        window.removeEventListener('popstate', handleBrowserNavigation);
        window.removeEventListener(RESOURCE_TAB_LOCATION_EVENT, onChange);
        stopWatchingVisits();
    };
}

function resourceLocationSnapshot(): ResourceTab {
    return activeTab;
}

function resourceLocationServerSnapshot(): ResourceTab {
    return 'details';
}

export function useResourceTab(commentsEnabled = true): {
    activeTab: ResourceTab;
    selectTab: (tab: ResourceTab) => void;
} {
    const tab = useSyncExternalStore(
        subscribeResourceLocation,
        resourceLocationSnapshot,
        resourceLocationServerSnapshot,
    );
    const resolvedTab: ResourceTab =
        !commentsEnabled && tab === 'comments' ? 'details' : tab;

    const selectTab = (next: ResourceTab) => {
        const target = nextResourceTabUrl(window.location.href, next);
        const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;

        activeTab = next;

        if (target !== current) {
            window.history.pushState(window.history.state, '', target);
        }

        window.dispatchEvent(new Event(RESOURCE_TAB_LOCATION_EVENT));
    };

    return { activeTab: resolvedTab, selectTab };
}
