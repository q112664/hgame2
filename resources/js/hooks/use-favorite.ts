import { useHttp, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { currentUrl } from '@/lib/current-url';
import { favorite } from '@/routes/resources';
import { show as favoriteShow } from '@/routes/resources/favorite';

type FavoriteToggleResponse = {
    favorited: boolean;
    message: string;
};

type FavoriteStateResponse = {
    favorited: boolean;
};

/**
 * Last known state per resource, kept outside the page props. Inertia restores
 * history entries from a snapshot, so props go stale as soon as the toggle stops
 * being a page visit; this map lets a restored page paint the right heart before
 * the background revalidation lands.
 */
const favoriteStateCache = new Map<string, boolean>();

type UseFavoriteOptions = {
    /** The resource id, must be a string for wayfinder param. */
    resourceId: string;
    /** Prop-supplied source of truth (server snapshot). */
    initialIsFavorited: boolean;
    /** Override fallback redirect target (defaults to current URL). */
    redirectPath?: string;
};

/**
 * Encapsulates the optimistic "favorite / unfavorite" interaction:
 *
 * - Toggles in the background through `useHttp`, so a click never waits on an
 *   Inertia visit (no progress bar, no partial reload of the detail payload).
 * - Revalidates from `GET …/favorite` on mount, when the resource changes and on
 *   browser back/forward, which is what keeps a restored history entry honest.
 * - Requires authentication; otherwise opens the auth dialog.
 * - Rolls back the optimistic state on error and surfaces a toast.
 */
export function useFavorite({
    resourceId,
    initialIsFavorited,
    redirectPath,
}: UseFavoriteOptions) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();
    const toggle = useHttp<Record<string, never>, FavoriteToggleResponse>(
        'post',
        favorite.url(resourceId),
        {},
    );

    // Seeded from the cache first (history restores), then from the server prop.
    const [serverFavorited, setServerFavorited] = useState<boolean>(
        () => favoriteStateCache.get(resourceId) ?? initialIsFavorited,
    );
    // Optimistic value for the click in flight; `null` means "use the server value".
    const [pendingFavorited, setPendingFavorited] = useState<boolean | null>(
        null,
    );

    const isFavorited = pendingFavorited ?? serverFavorited;

    const applyServerValue = useCallback(
        (value: boolean) => {
            favoriteStateCache.set(resourceId, value);
            setServerFavorited(value);
        },
        [resourceId],
    );

    useEffect(() => {
        if (!page.props.auth.user) {
            return;
        }

        let cancelled = false;

        const revalidate = () => {
            fetch(favoriteShow.url(resourceId), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<FavoriteStateResponse>)
                        : null,
                )
                .then((data) => {
                    if (
                        !cancelled &&
                        data !== null &&
                        typeof data.favorited === 'boolean'
                    ) {
                        applyServerValue(data.favorited);
                    }
                })
                .catch(() => {
                    // Offline or aborted: keep the value we already have.
                });
        };

        revalidate();

        // Inertia restores history entries without remounting or refetching.
        window.addEventListener('popstate', revalidate);

        return () => {
            cancelled = true;
            window.removeEventListener('popstate', revalidate);
        };
    }, [applyServerValue, page.props.auth.user, resourceId]);

    const toggleFavoriteState = useCallback(() => {
        // A second click while the first request is in flight would double-toggle;
        // ignore it rather than disabling (and dimming) the button.
        if (toggle.processing) {
            return;
        }

        if (!page.props.auth.user) {
            openAuthDialog('login', { redirect: redirectPath ?? currentUrl() });

            return;
        }

        const next = !isFavorited;

        setPendingFavorited(next);

        toggle.post(favorite.url(resourceId), {
            onSuccess: (response) => {
                applyServerValue(
                    typeof response?.favorited === 'boolean'
                        ? response.favorited
                        : next,
                );
                setPendingFavorited(null);

                toast.success(
                    response?.message ||
                        (next
                            ? 'Added to favorites.'
                            : 'Removed from favorites.'),
                );
            },
            onError: () => {
                setPendingFavorited(null);
                toast.error('Could not update favorites.');
            },
            onHttpException: (response) => {
                setPendingFavorited(null);

                // 401/419 mean the session is gone: a retry will never succeed, so
                // send the reader somewhere useful instead of showing a dead end.
                if (response.status === 401) {
                    openAuthDialog('login', {
                        redirect: redirectPath ?? currentUrl(),
                    });

                    return;
                }

                if (response.status === 419) {
                    toast.error('Your session expired. Reloading the page…');
                    window.location.reload();

                    return;
                }

                toast.error('Could not update favorites.');
            },
            onNetworkError: () => {
                setPendingFavorited(null);
                toast.error('Network error. Please try again.');
            },
        });
    }, [
        applyServerValue,
        isFavorited,
        openAuthDialog,
        page.props.auth.user,
        redirectPath,
        resourceId,
        toggle,
    ]);

    return useMemo(
        () => ({
            isFavorited,
            isToggling: toggle.processing,
            toggleFavorite: toggleFavoriteState,
        }),
        [isFavorited, toggle, toggleFavoriteState],
    );
}
