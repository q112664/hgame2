import { useSyncExternalStore } from 'react';

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia('(prefers-reduced-motion: reduce)');

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function prefersReducedMotion(): boolean {
    return mql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

/** Reacts to the OS "reduce motion" preference. SSR-safe (defaults to false). */
export function useReducedMotion(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        prefersReducedMotion,
        getServerSnapshot,
    );
}
