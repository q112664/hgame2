import { Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { SearchResults } from '@/components/site/search-results';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { SearchResource } from '@/hooks/use-resource-search';
import { useResourceSearch } from '@/hooks/use-resource-search';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';

type Props = {
    query: string;
    resources: SearchResource[];
};

export default function SearchPage({ query: initialQuery, resources }: Props) {
    const {
        query,
        setQuery,
        inputRef,
        stableResources,
        isPending,
        hasQuery,
        showPendingPlaceholder,
        showResults,
        showEmptyResults,
    } = useResourceSearch({ initialQuery, resources });

    return (
        <SiteLayout>
            <Head title="Search" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
                <div className="flex flex-col gap-6">
                    <label className="sr-only" htmlFor="site-search-input">
                        Search
                    </label>
                    <div className="relative mx-auto w-full max-w-2xl">
                        <Search className="pointer-events-none absolute top-1/2 left-4 z-10 size-4.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="site-search-input"
                            ref={inputRef}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search…"
                            className={cn(
                                'h-12 rounded-md border-border bg-card pr-11 pl-11 text-base shadow-none',
                                'ring-1 ring-border transition-[box-shadow,ring-color]',
                                'placeholder:text-muted-foreground/70',
                                'focus-visible:ring-2 focus-visible:ring-foreground/20',
                            )}
                            autoComplete="off"
                            autoFocus
                        />
                        {isPending ? (
                            <Spinner className="pointer-events-none absolute top-1/2 right-4 size-4.5 -translate-y-1/2 text-muted-foreground" />
                        ) : null}
                    </div>

                    {!hasQuery ? (
                        <p className="text-center text-base text-muted-foreground">
                            Type to search
                        </p>
                    ) : null}

                    {showPendingPlaceholder ? (
                        <div
                            className="flex justify-center py-10"
                            aria-busy="true"
                            aria-label="Searching"
                        >
                            <Spinner className="size-6 text-muted-foreground" />
                        </div>
                    ) : null}

                    {showEmptyResults ? (
                        <p className="text-center text-base text-muted-foreground">
                            No results
                        </p>
                    ) : null}

                    {showResults ? (
                        <SearchResults
                            resources={stableResources}
                            isPending={isPending}
                        />
                    ) : null}
                </div>
            </div>
        </SiteLayout>
    );
}
