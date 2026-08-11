import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { like as toggleLikeRoute } from '@/routes/resources';

type UseLikeOptions = {
    resourceId: string;
    initialIsLiked: boolean;
    initialLikesCount: number;
    only?: string[];
    redirectPath?: string;
};

/**
 * Optimistic like / unlike for a resource.
 * Requires auth; opens the login dialog for guests.
 */
export function useLike({
    resourceId,
    initialIsLiked,
    initialLikesCount,
    only = ['resource'],
    redirectPath,
}: UseLikeOptions) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();

    const [optimistic, setOptimistic] = useState({
        sourceLiked: initialIsLiked,
        sourceCount: initialLikesCount,
        isLiked: initialIsLiked,
        likesCount: initialLikesCount,
    });
    const [isToggling, setIsToggling] = useState(false);

    const serverInSync =
        optimistic.sourceLiked === initialIsLiked &&
        optimistic.sourceCount === initialLikesCount;

    const isLiked = serverInSync ? optimistic.isLiked : initialIsLiked;
    const likesCount = serverInSync ? optimistic.likesCount : initialLikesCount;

    const toggleLike = useCallback(() => {
        if (!page.props.auth.user) {
            openAuthDialog('login', { redirect: redirectPath ?? page.url });

            return;
        }

        const nextLiked = !isLiked;
        const nextCount = Math.max(0, likesCount + (nextLiked ? 1 : -1));

        setOptimistic({
            sourceLiked: initialIsLiked,
            sourceCount: initialLikesCount,
            isLiked: nextLiked,
            likesCount: nextCount,
        });
        setIsToggling(true);

        router.post(
            toggleLikeRoute.url(resourceId),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only,
                onError: () => {
                    setOptimistic({
                        sourceLiked: initialIsLiked,
                        sourceCount: initialLikesCount,
                        isLiked: !nextLiked,
                        likesCount: Math.max(
                            0,
                            nextCount + (nextLiked ? -1 : 1),
                        ),
                    });
                },
                onFinish: () => setIsToggling(false),
            },
        );
    }, [
        initialIsLiked,
        initialLikesCount,
        isLiked,
        likesCount,
        only,
        openAuthDialog,
        page.props.auth.user,
        page.url,
        redirectPath,
        resourceId,
    ]);

    return useMemo(
        () => ({
            isLiked,
            likesCount,
            isToggling,
            toggleLike,
        }),
        [isLiked, likesCount, isToggling, toggleLike],
    );
}
