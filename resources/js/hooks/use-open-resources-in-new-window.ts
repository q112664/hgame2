import { useCallback, useSyncExternalStore } from 'react';

export const OPEN_RESOURCES_IN_NEW_WINDOW_KEY = 'resources.openInNewWindow';

const listeners = new Set<() => void>();

function notify(): void {
    listeners.forEach((listener) => listener());
}

function readStored(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return (
            window.localStorage.getItem(OPEN_RESOURCES_IN_NEW_WINDOW_KEY) ===
            '1'
        );
    } catch {
        return false;
    }
}

let current = readStored();

if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (event.key !== OPEN_RESOURCES_IN_NEW_WINDOW_KEY) {
            return;
        }

        current = event.newValue === '1';
        notify();
    });
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function useOpenResourcesInNewWindow(): {
    openInNewWindow: boolean;
    setOpenInNewWindow: (enabled: boolean) => void;
} {
    const openInNewWindow = useSyncExternalStore(
        subscribe,
        () => current,
        () => false,
    );

    const setOpenInNewWindow = useCallback((enabled: boolean) => {
        current = enabled;

        try {
            window.localStorage.setItem(
                OPEN_RESOURCES_IN_NEW_WINDOW_KEY,
                enabled ? '1' : '0',
            );
        } catch {
            // Ignore private mode / blocked storage.
        }

        notify();
    }, []);

    return { openInNewWindow, setOpenInNewWindow };
}
