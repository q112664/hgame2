import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { show as resourceDetails } from '@/routes/resources';

export type ResourceTab = 'details' | 'downloads' | 'screenshots' | 'comments';

const RESOURCE_TAB_LOCATION_EVENT = 'resource-tab-location';

const resourceTabValues: readonly string[] = [
    'details',
    'downloads',
    'screenshots',
    'comments',
];

function isResourceTab(value: string | null): value is ResourceTab {
    return value !== null && resourceTabValues.includes(value);
}

function parseSearch(search: string): URLSearchParams {
    return new URLSearchParams(
        search.startsWith('?') ? search.slice(1) : search,
    );
}

/**
 * The tab lives in `?tab=` because a query string survives the round trip a
 * fragment does not: `back()` redirects replay it from the Referer, and the
 * XHR that follows a redirect keeps it in the response URL. Fragments are
 * reserved for in-document anchors (`#comment-12`), which is what they mean.
 *
 * `#downloads` and `?focus=` are still honoured so links shared before the
 * query contract keep opening the tab they name.
 */
export function parseResourceTab(hash: string, search = ''): ResourceTab {
    const params = parseSearch(search);
    const requested = params.get('tab');

    if (isResourceTab(requested)) {
        return requested;
    }

    const value = hash.startsWith('#') ? hash.slice(1) : hash;

    if (isResourceTab(value)) {
        return value;
    }

    if (value.startsWith('comment-')) {
        return 'comments';
    }

    // Read the same way the reviews panel does, so a junk `focus` does not open
    // a tab with nothing in it to scroll to.
    const focus = Number(params.get('focus') ?? '');

    if (Number.isInteger(focus) && focus > 0) {
        return 'comments';
    }

    const page = Number(params.get('page') ?? '');

    if (Number.isInteger(page) && page > 1) {
        return 'comments';
    }

    return 'details';
}

export function nextResourceTabUrl(href: string, tab: ResourceTab): string {
    const url = new URL(href, 'http://localhost');

    if (tab === 'details') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', tab);
    }

    if (tab !== 'comments') {
        url.searchParams.delete('page');
        url.searchParams.delete('focus');
    }

    // The tab is no longer a fragment, and a stale comment anchor would send
    // the browser scrolling into a panel that is about to be hidden.
    url.hash = '';

    return `${url.pathname}${url.search}`;
}

export function resourceTabHref(resourceId: string, tab: ResourceTab): string {
    return resourceDetails.url(resourceId, {
        query: tab === 'details' ? {} : { tab },
    });
}

export function commentsPageUrl(resourceId: string, page: number): string {
    return resourceDetails.url(resourceId, {
        query: { tab: 'comments', ...(page > 1 ? { page } : {}) },
    });
}

function currentLocation(): string {
    return `${window.location.pathname}${window.location.search}${window.location.hash}`;
}

function resourceLocationSnapshot(): ResourceTab {
    return parseResourceTab(window.location.hash, window.location.search);
}

/**
 * The tab the server rendered, so hydration starts from the markup it is
 * hydrating instead of repainting the default panel first. A legacy `#`-anchor
 * link is invisible to the server, so those still render details and switch
 * once the client reads the URL.
 */
function resourceLocationServerSnapshot(
    initialTab: ResourceTab,
): () => ResourceTab {
    return () => initialTab;
}

function subscribeResourceLocation(onChange: () => void): () => void {
    window.addEventListener('hashchange', onChange);
    window.addEventListener('popstate', onChange);
    window.addEventListener(RESOURCE_TAB_LOCATION_EVENT, onChange);

    // A query-only visit (pagination, or an Inertia redirect that lands on a
    // different tab) changes the URL through the history API, which fires no
    // popstate, so Inertia has to be the one to tell us the URL moved. It stays
    // quiet for the visits it performs as a replacement, which nothing on this
    // page does today.
    const stopWatchingVisits = router.on('navigate', onChange);

    return () => {
        window.removeEventListener('hashchange', onChange);
        window.removeEventListener('popstate', onChange);
        window.removeEventListener(RESOURCE_TAB_LOCATION_EVENT, onChange);
        stopWatchingVisits();
    };
}

export function useResourceTab(
    commentsEnabled = true,
    initialTab: ResourceTab = 'details',
): {
    activeTab: ResourceTab;
    selectTab: (tab: ResourceTab) => void;
} {
    const tab = useSyncExternalStore(
        subscribeResourceLocation,
        resourceLocationSnapshot,
        resourceLocationServerSnapshot(initialTab),
    );
    const resolvedTab: ResourceTab =
        !commentsEnabled && tab === 'comments' ? 'details' : tab;

    /**
     * A tab is a view switch on a page the reader already has open, not a
     * navigation: the URL is replaced rather than pushed, so Back leaves the
     * page behind instead of walking back through every tab that was peeked at.
     * The URL still names the tab, so a reload, a bookmark or a shared link
     * lands on it.
     */
    const selectTab = (next: ResourceTab) => {
        const target = nextResourceTabUrl(window.location.href, next);

        if (target !== currentLocation()) {
            window.history.replaceState(window.history.state, '', target);
        }

        window.dispatchEvent(new Event(RESOURCE_TAB_LOCATION_EVENT));
    };

    return { activeTab: resolvedTab, selectTab };
}
