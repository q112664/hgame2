import { Head } from '@inertiajs/react';
import { Search, SearchX } from 'lucide-react';
import {
    SearchResults,
    SearchResultsSkeleton,
} from '@/components/site/search-results';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SitePagination } from '@/components/site/site-pagination';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { PaginatedSearchResources } from '@/hooks/use-resource-search';
import { useResourceSearch } from '@/hooks/use-resource-search';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import { search as searchRoute } from '@/routes';

type Props = {
    query: string;
    resources: PaginatedSearchResources;
};

function scrollToSearchResults() {
    document.getElementById('search-results')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

export default function SearchPage({ query: initialQuery, resources }: Props) {
    const {
        query,
        setQuery,
        inputRef,
        stableResources,
        isPending,
        showPendingPlaceholder,
        showResults,
        showEmptyResults,
    } = useResourceSearch({ initialQuery, resources });

    return (
        <SiteLayout>
            <Head title="Search" />

            <SitePageContainer className="gap-8">
                <header>
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        Search
                    </h1>
                </header>

                <div className="flex flex-col gap-6">
                    <label className="sr-only" htmlFor="site-search-input">
                        Search
                    </label>
                    <div className="relative w-full max-w-3xl">
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

                    {showPendingPlaceholder ? <SearchResultsSkeleton /> : null}

                    {showEmptyResults ? (
                        <div className="flex min-h-56 flex-col items-center justify-center gap-3 rounded-md border border-dashed border-border bg-card/50 px-6 text-center">
                            <SearchX className="size-6 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                No matches for “{query.trim()}”
                            </p>
                        </div>
                    ) : null}

                    {showResults ? (
                        <div
                            id="search-results"
                            className="flex scroll-mt-20 flex-col gap-8"
                        >
                            <SearchResults
                                resources={stableResources.data}
                                isPending={isPending}
                            />

                            {!isPending ? (
                                <SitePagination
                                    pagination={stableResources}
                                    pageUrl={(page) =>
                                        searchRoute.url({
                                            query: {
                                                q: query.trim(),
                                                page,
                                            },
                                        })
                                    }
                                    ariaLabel="Search pagination"
                                    only={['query', 'resources']}
                                    onSuccess={scrollToSearchResults}
                                />
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
