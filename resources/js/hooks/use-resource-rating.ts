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
 * Optimistic resource rating (1–10 scale) with auth-gated submit.
 * `rate(score)` always sets/updates; `clear()` removes the rating.
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
    const isAuthenticated = Boolean(page.props.auth.user);

    const requireAuth = useCallback(() => {
        if (isAuthenticated) {
            return true;
        }

        openAuthDialog('login', {
            redirect: redirectPath ?? page.url,
        });

        return false;
    }, [isAuthenticated, openAuthDialog, page.url, redirectPath]);

    const rate = useCallback(
        (score: number) => {
            if (!requireAuth()) {
                return;
            }

            const baseline = optimistic ?? serverSnapshot;

            if (baseline.userRating === score) {
                return;
            }

            setOptimistic(optimisticAfterScore(baseline, score));
            setIsSaving(true);

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
        [only, optimistic, requireAuth, resourceId, serverSnapshot],
    );

    const clear = useCallback(() => {
        if (!requireAuth()) {
            return;
        }

        const baseline = optimistic ?? serverSnapshot;

        if (baseline.userRating === null) {
            return;
        }

        setOptimistic(optimisticAfterClear(baseline));
        setIsSaving(true);

        router.delete(clearRating.url(resourceId), {
            preserveScroll: true,
            preserveState: true,
            only,
            onError: () => setOptimistic(baseline),
            onFinish: () => setIsSaving(false),
        });
    }, [only, optimistic, requireAuth, resourceId, serverSnapshot]);

    return useMemo(
        () => ({
            average: snapshot.average,
            count: snapshot.count,
            userRating: snapshot.userRating,
            isSaving,
            isAuthenticated,
            requireAuth,
            rate,
            clear,
        }),
        [
            clear,
            isAuthenticated,
            isSaving,
            rate,
            requireAuth,
            snapshot.average,
            snapshot.count,
            snapshot.userRating,
        ],
    );
}
