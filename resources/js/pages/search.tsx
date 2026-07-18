import { Head } from '@inertiajs/react';
import { Search, SearchX, X } from 'lucide-react';
import {
    SearchResults,
    SearchResultsSkeleton,
} from '@/components/site/search-results';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SitePagination } from '@/components/site/site-pagination';
import { Button } from '@/components/ui/button';
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

function formatResultCount(total: number): string {
    return total === 1 ? '1 result' : `${total.toLocaleString()} results`;
}

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

    const clearQuery = () => {
        setQuery('');
        inputRef.current?.focus();
    };

    return (
        <SiteLayout>
            <Head title="Search" />

            <SitePageContainer className="gap-8">
                <header className="flex flex-col gap-2">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        Search
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Find resources by title, tag, category, platform, or
                        language
                    </p>
                </header>

                <div className="flex flex-col gap-6">
                    <div className="flex flex-col gap-2.5">
                        <label className="sr-only" htmlFor="site-search-input">
                            Search
                        </label>
                        <div
                            className={cn(
                                'relative flex w-full items-center rounded-lg border border-border bg-card shadow-none',
                                'ring-1 ring-border transition-[box-shadow,ring-color]',
                                'focus-within:ring-2 focus-within:ring-foreground/15',
                            )}
                        >
                            <Search className="pointer-events-none absolute top-1/2 left-4 size-4.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="site-search-input"
                                ref={inputRef}
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape' && query !== '') {
                                        event.preventDefault();
                                        clearQuery();
                                    }
                                }}
                                placeholder="Search titles, tags, categories…"
                                className={cn(
                                    'h-14 border-0 bg-transparent pr-24 pl-11 text-base shadow-none md:text-base',
                                    'ring-0 focus-visible:ring-0',
                                    'placeholder:text-muted-foreground/70',
                                )}
                                autoComplete="off"
                                autoFocus
                                enterKeyHint="search"
                            />
                            <div className="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-0.5">
                                {isPending ? (
                                    <Spinner className="mr-1.5 size-4 text-muted-foreground" />
                                ) : null}
                                {query !== '' ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-muted-foreground hover:text-foreground"
                                        aria-label="Clear search"
                                        onClick={clearQuery}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <div className="flex min-h-5 items-center justify-between gap-3 px-0.5 text-xs text-muted-foreground">
                            {hasQuery ? (
                                <p aria-live="polite">
                                    {isPending
                                        ? 'Searching…'
                                        : `${formatResultCount(stableResources.total)} for “${query.trim()}”`}
                                </p>
                            ) : (
                                <p>Start typing to search published resources</p>
                            )}
                            {query !== '' ? (
                                <p className="hidden sm:block">
                                    Press Esc to clear
                                </p>
                            ) : null}
                        </div>
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
