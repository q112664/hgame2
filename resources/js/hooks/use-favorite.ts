import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { favorite as toggleFavorite } from '@/routes/resources';

type UseFavoriteOptions = {
    /** The resource id, must be a string for wayfinder param. */
    resourceId: string;
    /** Prop-supplied source of truth (server snapshot). */
    initialIsFavorited: boolean;
    /**
     * Inertia partial-reload key list. Defaults to `['resource']`,
     * matching the resource detail layout.
     */
    only?: string[];
    /** Override fallback redirect target (defaults to current URL). */
    redirectPath?: string;
};

/**
 * Encapsulates the optimistic "favorite / unfavorite" interaction:
 *
 * - Reconciles server prop snapshot with optimistic local state.
 * - Requires authentication; otherwise opens the auth dialog.
 * - Performs a POST to the toggle endpoint with partial reload.
 * - Rolls back the optimistic state on error and surfaces a flag.
 *
 * Multiple components on the same page can call this individually;
 * each owns its own optimistic state but shares the server snapshot
 * through `usePage().props`.
 */
export function useFavorite({
    resourceId,
    initialIsFavorited,
    only = ['resource'],
    redirectPath,
}: UseFavoriteOptions) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();

    // Optimistic toggle relative to the server snapshot that was current when
    // the user clicked. When `initialIsFavorited` changes (partial reload /
    // navigation), prefer the server value until the next local toggle.
    const [optimistic, setOptimistic] = useState({
        source: initialIsFavorited,
        value: initialIsFavorited,
    });
    const [isToggling, setIsToggling] = useState(false);

    const isFavorited =
        optimistic.source === initialIsFavorited
            ? optimistic.value
            : initialIsFavorited;

    const toggleFavoriteState = useCallback(() => {
        if (!page.props.auth.user) {
            openAuthDialog('login', { redirect: redirectPath ?? page.url });

            return;
        }

        const next = !isFavorited;

        setOptimistic({
            source: initialIsFavorited,
            value: next,
        });
        setIsToggling(true);

        router.post(
            toggleFavorite.url(resourceId),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only,
                onError: () => {
                    setOptimistic({
                        source: initialIsFavorited,
                        value: !next,
                    });
                },
                onFinish: () => setIsToggling(false),
            },
        );
    }, [
        initialIsFavorited,
        isFavorited,
        only,
        openAuthDialog,
        page.props.auth.user,
        page.url,
        redirectPath,
        resourceId,
    ]);

    return useMemo(
        () => ({
            isFavorited,
            isToggling,
            toggleFavorite: toggleFavoriteState,
        }),
        [isFavorited, isToggling, toggleFavoriteState],
    );
}
