import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import { search } from '@/routes';
import { details as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type SearchResource = Pick<GameCard, 'id' | 'title' | 'thumbnail'> & {
    subtitle: string | null;
};

type Props = {
    query: string;
    resources: SearchResource[];
};

export default function SearchPage({ query: initialQuery, resources }: Props) {
    const [query, setQuery] = useState(initialQuery);
    const [isSearching, setIsSearching] = useState(false);
    const [stableResources, setStableResources] = useState(resources);
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

    useEffect(() => {
        if (!hasQuery) {
            setStableResources([]);

            return;
        }

        if (!isPending) {
            setStableResources(resources);
        }
    }, [hasQuery, isPending, resources]);

    const showPendingPlaceholder = isPending && stableResources.length === 0;
    const showResults = hasQuery && stableResources.length > 0;
    const showEmptyResults =
        hasQuery && !isPending && stableResources.length === 0;

    return (
        <SiteLayout>
            <Head title="Search" />

            <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-8 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                    <label className="sr-only" htmlFor="site-search-input">
                        Search
                    </label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="site-search-input"
                            ref={inputRef}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search…"
                            className={cn(
                                'h-11 rounded-md border-foreground/10 bg-card pr-10 pl-10 text-sm shadow-none',
                                'ring-1 ring-foreground/10 transition-[box-shadow,ring-color]',
                                'placeholder:text-muted-foreground/70',
                                'focus-visible:ring-2 focus-visible:ring-foreground/20',
                            )}
                            autoComplete="off"
                            autoFocus
                        />
                        {isPending ? (
                            <Spinner className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        ) : null}
                    </div>

                    {!hasQuery ? (
                        <p className="text-center text-sm text-muted-foreground">
                            Type to search
                        </p>
                    ) : null}

                    {showPendingPlaceholder ? (
                        <div
                            className="flex justify-center py-8"
                            aria-busy="true"
                            aria-label="Searching"
                        >
                            <Spinner className="size-5 text-muted-foreground" />
                        </div>
                    ) : null}

                    {showEmptyResults ? (
                        <p className="text-center text-sm text-muted-foreground">
                            No results
                        </p>
                    ) : null}

                    {showResults ? (
                        <ul
                            className={cn(
                                'divide-y divide-foreground/8 transition-opacity duration-150',
                                isPending ? 'opacity-50' : 'opacity-100',
                            )}
                            aria-busy={isPending}
                        >
                            {stableResources.map((resource) => (
                                <li key={resource.id}>
                                    <Link
                                        href={resourceDetails(resource.id)}
                                        className={cn(
                                            'group flex items-center gap-3 py-3',
                                            'transition-opacity duration-150 hover:opacity-80',
                                            isPending && 'pointer-events-none',
                                        )}
                                        prefetch={!isPending}
                                        tabIndex={isPending ? -1 : undefined}
                                    >
                                        <div className="aspect-video w-20 shrink-0 overflow-hidden rounded bg-muted sm:w-24">
                                            <img
                                                src={resource.thumbnail}
                                                alt=""
                                                className="size-full object-cover"
                                                loading="lazy"
                                                referrerPolicy="no-referrer"
                                            />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <span className="line-clamp-1 text-sm font-medium text-foreground">
                                                {resource.title}
                                            </span>
                                            {resource.subtitle ? (
                                                <span className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                                    {resource.subtitle}
                                                </span>
                                            ) : null}
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            </div>
        </SiteLayout>
    );
}
