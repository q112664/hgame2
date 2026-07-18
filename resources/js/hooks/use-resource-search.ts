import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PaginatedData } from '@/components/site/site-pagination';
import { search } from '@/routes';
import type { GameCard } from '@/types/resources';

export type SearchResource = GameCard;
export type PaginatedSearchResources = PaginatedData<SearchResource>;

type Props = {
    initialQuery: string;
    resources: PaginatedSearchResources;
};

export function useResourceSearch({ initialQuery, resources }: Props) {
    const [query, setQuery] = useState(initialQuery);
    const [isSearching, setIsSearching] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const isFirstRender = useRef(true);
    const requestId = useRef(0);

    useEffect(() => {
        const frame = requestAnimationFrame(() => inputRef.current?.focus());

        return () => cancelAnimationFrame(frame);
    }, []);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const trimmed = query.trim();
        const timer = window.setTimeout(() => {
            const currentRequestId = ++requestId.current;

            router.get(
                search.url({
                    query: trimmed === '' ? {} : { q: trimmed },
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['query', 'resources'],
                    onStart: () => {
                        if (currentRequestId === requestId.current) {
                            setIsSearching(true);
                        }
                    },
                    onFinish: () => {
                        if (currentRequestId === requestId.current) {
                            setIsSearching(false);
                        }
                    },
                },
            );
        }, 300);

        return () => window.clearTimeout(timer);
    }, [query]);

    const trimmedQuery = query.trim();
    const hasQuery = trimmedQuery !== '';
    const isPending =
        hasQuery && (trimmedQuery !== initialQuery.trim() || isSearching);

    return {
        query,
        setQuery,
        inputRef,
        stableResources: resources,
        isPending,
        hasQuery,
        showPendingPlaceholder: isPending && resources.data.length === 0,
        showResults: hasQuery && resources.data.length > 0,
        showEmptyResults: hasQuery && !isPending && resources.data.length === 0,
    };
}
