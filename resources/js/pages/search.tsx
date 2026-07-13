import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Search, SearchX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
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

const searchHints = [
    'Title',
    'Subtitle',
    'Tags',
    'Category',
    'Platform',
    'Language',
] as const;

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

    const showSkeleton = isPending && stableResources.length === 0;
    const showResults = hasQuery && stableResources.length > 0;
    const showEmptyResults =
        hasQuery && !isPending && stableResources.length === 0;

    return (
        <SiteLayout>
            <Head title="Search" />

            <section className="border-b border-border/60">
                <div className="mx-auto flex w-full max-w-[90rem] flex-col gap-8 px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
                    <div className="flex max-w-2xl flex-col gap-3">
                        <h1 className="font-heading text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                            Search
                        </h1>
                        <p className="text-sm text-muted-foreground sm:text-base">
                            Find visual novels and galgame resources across
                            titles, tags, categories, platforms, and languages.
                        </p>
                    </div>

                    <div className="flex max-w-2xl flex-col gap-4">
                        <label className="sr-only" htmlFor="site-search-input">
                            Search resources
                        </label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-4 z-10 size-5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="site-search-input"
                                ref={inputRef}
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Type a keyword…"
                                className={cn(
                                    'h-14 rounded-md border-foreground/10 bg-card pr-4 pl-12 text-base shadow-none',
                                    'ring-1 ring-foreground/10 transition-[box-shadow,ring-color]',
                                    'placeholder:text-muted-foreground/70',
                                    'focus-visible:ring-2 focus-visible:ring-foreground/20',
                                )}
                                autoComplete="off"
                                autoFocus
                            />
                            <div
                                aria-hidden
                                className={cn(
                                    'pointer-events-none absolute inset-x-3 bottom-0 h-0.5 overflow-hidden rounded-full',
                                    isPending ? 'opacity-100' : 'opacity-0',
                                )}
                            >
                                <div className="site-search-progress h-full w-1/3 rounded-full bg-foreground/55" />
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {searchHints.map((hint) => (
                                <span
                                    key={hint}
                                    className="inline-flex h-7 items-center rounded-md bg-background px-2.5 text-xs font-medium text-muted-foreground ring-1 ring-foreground/10"
                                >
                                    {hint}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto w-full max-w-[90rem] px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
                <div className="flex flex-col gap-5">
                    {hasQuery ? (
                        <div className="flex items-baseline justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                {isPending
                                    ? 'Searching…'
                                    : showEmptyResults
                                      ? `No results for “${trimmedQuery}”`
                                      : `${stableResources.length} result${stableResources.length === 1 ? '' : 's'}`}
                            </p>
                            {!isPending && stableResources.length > 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    Matching “{trimmedQuery}”
                                </p>
                            ) : null}
                        </div>
                    ) : (
                        <div className="flex flex-col items-start gap-3 rounded-md bg-card px-5 py-10 ring-1 ring-foreground/10 sm:px-8">
                            <div className="flex size-11 items-center justify-center rounded-md bg-muted text-muted-foreground ring-1 ring-foreground/10">
                                <Search className="size-5" />
                            </div>
                            <div className="flex max-w-md flex-col gap-1.5">
                                <p className="font-heading text-lg font-semibold tracking-tight text-foreground">
                                    Start typing to search
                                </p>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Results appear here after you enter a
                                    keyword. Try a game title, tag, category,
                                    platform, or language.
                                </p>
                            </div>
                        </div>
                    )}

                    {showSkeleton ? (
                        <ul className="flex flex-col gap-3" aria-hidden>
                            {Array.from({ length: 4 }).map((_, index) => (
                                <li
                                    key={index}
                                    className="flex items-center gap-4 rounded-md bg-card p-3 ring-1 ring-foreground/10 sm:p-4"
                                >
                                    <Skeleton className="aspect-[16/12] w-28 shrink-0 rounded-md sm:w-32" />
                                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                                        <Skeleton className="h-4 w-2/3 max-w-64" />
                                        <Skeleton className="h-3 w-1/2 max-w-48" />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : null}

                    {showEmptyResults ? (
                        <div className="flex flex-col items-start gap-3 rounded-md bg-card px-5 py-10 ring-1 ring-foreground/10 sm:px-8">
                            <div className="flex size-11 items-center justify-center rounded-md bg-muted text-muted-foreground ring-1 ring-foreground/10">
                                <SearchX className="size-5" />
                            </div>
                            <div className="flex max-w-md flex-col gap-1.5">
                                <p className="font-heading text-lg font-semibold tracking-tight text-foreground">
                                    Nothing matched
                                </p>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    No published resources matched “
                                    {trimmedQuery}”. Try another keyword or a
                                    shorter phrase.
                                </p>
                            </div>
                        </div>
                    ) : null}

                    {showResults ? (
                        <ul
                            className={cn(
                                'flex flex-col gap-3 transition-opacity duration-150',
                                isPending ? 'opacity-55' : 'opacity-100',
                            )}
                            aria-busy={isPending}
                        >
                            {stableResources.map((resource) => (
                                <li key={resource.id}>
                                    <Link
                                        href={resourceDetails(resource.id)}
                                        className={cn(
                                            'group flex items-center gap-4 rounded-md bg-card p-3 ring-1 ring-foreground/10',
                                            'transition-[ring-color,transform] duration-150',
                                            'hover:ring-foreground/20 sm:p-4',
                                            isPending && 'pointer-events-none',
                                        )}
                                        prefetch={!isPending}
                                        tabIndex={isPending ? -1 : undefined}
                                    >
                                        <div className="aspect-[16/12] w-28 shrink-0 overflow-hidden rounded-md bg-muted sm:w-32">
                                            <img
                                                src={resource.thumbnail}
                                                alt=""
                                                className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                                                loading="lazy"
                                                referrerPolicy="no-referrer"
                                            />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <span className="line-clamp-2 font-heading text-sm font-semibold tracking-tight text-foreground sm:text-base">
                                                {resource.title}
                                            </span>
                                            {resource.subtitle ? (
                                                <span className="mt-1 line-clamp-1 text-xs text-muted-foreground sm:text-sm">
                                                    {resource.subtitle}
                                                </span>
                                            ) : null}
                                        </div>
                                        <ArrowRight className="size-4 shrink-0 text-muted-foreground opacity-0 transition-all duration-150 group-hover:translate-x-0.5 group-hover:opacity-100" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            </section>
        </SiteLayout>
    );
}
