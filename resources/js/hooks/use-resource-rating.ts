import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import {
    destroy as clearRating,
    store as upsertRating,
} from '@/routes/resources/rating';

type UseResourceRatingOptions = {
    resourceId: string;
    initialAverage: number | null;
    initialCount: number;
    initialUserRating: number | null;
    only?: string[];
    redirectPath?: string;
};

type RatingSnapshot = {
    average: number | null;
    count: number;
    userRating: number | null;
};

function optimisticAfterScore(
    snapshot: RatingSnapshot,
    score: number,
): RatingSnapshot {
    const previous = snapshot.userRating;
    const nextCount = previous === null ? snapshot.count + 1 : snapshot.count;
    const total =
        (snapshot.average ?? 0) * snapshot.count - (previous ?? 0) + score;
    const average =
        nextCount > 0 ? Math.round((total / nextCount) * 10) / 10 : null;

    return {
        average,
        count: nextCount,
        userRating: score,
    };
}

function optimisticAfterClear(snapshot: RatingSnapshot): RatingSnapshot {
    if (snapshot.userRating === null || snapshot.count <= 0) {
        return {
            average: snapshot.average,
            count: snapshot.count,
            userRating: null,
        };
    }

    const nextCount = snapshot.count - 1;
    const total =
        (snapshot.average ?? 0) * snapshot.count - snapshot.userRating;
    const average =
        nextCount > 0 ? Math.round((total / nextCount) * 10) / 10 : null;

    return {
        average,
        count: nextCount,
        userRating: null,
    };
}

/**
 * Optimistic resource rating (1–5 stars) with auth-gated submit.
 * Clicking the current score again clears the rating.
 */
export function useResourceRating({
    resourceId,
    initialAverage,
    initialCount,
    initialUserRating,
    only = ['resource'],
    redirectPath,
}: UseResourceRatingOptions) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();

    const serverSnapshot = useMemo(
        (): RatingSnapshot => ({
            average: initialAverage,
            count: initialCount,
            userRating: initialUserRating,
        }),
        [initialAverage, initialCount, initialUserRating],
    );

    const [optimistic, setOptimistic] = useState<RatingSnapshot | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        setOptimistic(null);
    }, [serverSnapshot]);

    const snapshot = optimistic ?? serverSnapshot;

    const rate = useCallback(
        (score: number) => {
            if (!page.props.auth.user) {
                openAuthDialog('login', {
                    redirect: redirectPath ?? page.url,
                });

                return;
            }

            const baseline = optimistic ?? serverSnapshot;
            const shouldClear = baseline.userRating === score;
            const next = shouldClear
                ? optimisticAfterClear(baseline)
                : optimisticAfterScore(baseline, score);

            setOptimistic(next);
            setIsSaving(true);

            if (shouldClear) {
                router.delete(clearRating.url(resourceId), {
                    preserveScroll: true,
                    preserveState: true,
                    only,
                    onError: () => setOptimistic(baseline),
                    onFinish: () => setIsSaving(false),
                });

                return;
            }

            router.post(
                upsertRating.url(resourceId),
                { score },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only,
                    onError: () => setOptimistic(baseline),
                    onFinish: () => setIsSaving(false),
                },
            );
        },
        [
            only,
            openAuthDialog,
            optimistic,
            page.props.auth.user,
            page.url,
            redirectPath,
            resourceId,
            serverSnapshot,
        ],
    );

    return useMemo(
        () => ({
            average: snapshot.average,
            count: snapshot.count,
            userRating: snapshot.userRating,
            isSaving,
            rate,
        }),
        [isSaving, rate, snapshot.average, snapshot.count, snapshot.userRating],
    );
}
