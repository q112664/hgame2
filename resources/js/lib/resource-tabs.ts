import { useSyncExternalStore } from 'react';
import { show as resourceDetails } from '@/routes/resources';

export type ResourceTab = 'details' | 'downloads' | 'screenshots' | 'comments';

const RESOURCE_TAB_LOCATION_EVENT = 'resource-tab-location';

export function parseResourceTab(
    hash: string,
    search = '',
    commentsEnabled = true,
): ResourceTab {
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

    if (!commentsEnabled && tab === 'comments') {
        return 'details';
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

function subscribeResourceLocation(onChange: () => void): () => void {
    window.addEventListener('hashchange', onChange);
    window.addEventListener('popstate', onChange);
    window.addEventListener(RESOURCE_TAB_LOCATION_EVENT, onChange);

    return () => {
        window.removeEventListener('hashchange', onChange);
        window.removeEventListener('popstate', onChange);
        window.removeEventListener(RESOURCE_TAB_LOCATION_EVENT, onChange);
    };
}

function resourceLocationSnapshot(): string {
    return `${window.location.hash}\0${window.location.search}`;
}

function resourceLocationServerSnapshot(): string {
    return '';
}

export function useResourceTab(commentsEnabled = true): {
    activeTab: ResourceTab;
    selectTab: (tab: ResourceTab) => void;
} {
    const snapshot = useSyncExternalStore(
        subscribeResourceLocation,
        resourceLocationSnapshot,
        resourceLocationServerSnapshot,
    );
    const separator = snapshot.indexOf('\0');
    const hash = separator === -1 ? snapshot : snapshot.slice(0, separator);
    const search = separator === -1 ? '' : snapshot.slice(separator + 1);
    const activeTab = parseResourceTab(hash, search, commentsEnabled);

    const selectTab = (tab: ResourceTab) => {
        const next = nextResourceTabUrl(window.location.href, tab);
        const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;

        if (next === current) {
            return;
        }

        window.history.pushState(window.history.state, '', next);
        window.dispatchEvent(new Event(RESOURCE_TAB_LOCATION_EVENT));
    };

    return { activeTab, selectTab };
}
