import { useHttp, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { currentUrl } from '@/lib/current-url';
import { favorite as toggleFavorite } from '@/routes/resources';

type FavoriteResponse = {
    favorited: boolean;
    message: string;
};

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
 * - Reconciles the server prop snapshot with optimistic local state.
 * - Requires authentication; otherwise opens the auth dialog.
 * - Posts in the background through `useHttp`, so a click never waits on an
 *   Inertia visit: no progress bar and no partial reload of the detail payload.
 * - Rolls back the optimistic state on error and surfaces a toast.
 *
 * Multiple components on the same page can call this individually; each owns its
 * own optimistic state but shares the server snapshot through `usePage().props`.
 */
export function useFavorite({
    resourceId,
    initialIsFavorited,
    redirectPath,
}: UseFavoriteOptions) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();
    const toggle = useHttp<Record<string, never>, FavoriteResponse>(
        'post',
        toggleFavorite.url(resourceId),
        {},
    );

    // Optimistic toggle relative to the server snapshot that was current when
    // the user clicked. When `initialIsFavorited` changes (navigation), prefer
    // the server value until the next local toggle.
    const [optimistic, setOptimistic] = useState({
        source: initialIsFavorited,
        value: initialIsFavorited,
    });

    const isFavorited =
        optimistic.source === initialIsFavorited
            ? optimistic.value
            : initialIsFavorited;

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

        setOptimistic({
            source: initialIsFavorited,
            value: next,
        });
        toggle.post(toggleFavorite.url(resourceId), {
            onSuccess: (response) => {
                // Trust the server when it disagrees with the optimistic guess.
                if (typeof response?.favorited === 'boolean') {
                    setOptimistic({
                        source: initialIsFavorited,
                        value: response.favorited,
                    });
                }

                toast.success(
                    response?.message ||
                        (next
                            ? 'Added to favorites.'
                            : 'Removed from favorites.'),
                );
            },
            onError: () => {
                setOptimistic({
                    source: initialIsFavorited,
                    value: !next,
                });
                toast.error('Could not update favorites.');
            },
            onHttpException: (response) => {
                setOptimistic({
                    source: initialIsFavorited,
                    value: !next,
                });

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
                setOptimistic({
                    source: initialIsFavorited,
                    value: !next,
                });
                toast.error('Network error. Please try again.');
            },
        });
    }, [
        initialIsFavorited,
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
