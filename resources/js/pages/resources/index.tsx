import { Library, RotateCcw, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { ResourceCard } from '@/components/site/resource-card';
import {
    DEFAULT_FILTERS,
    FilterMenu,
    SortMenu,
    TagFilterDialog,
    filterControlClassName,
    visitFilters,
} from '@/components/site/resource-filter-controls';
import type {
    FilterOptions,
    ResourceFilters,
} from '@/components/site/resource-filter-controls';
import { ResourcePagination } from '@/components/site/resource-pagination';
import type { PaginatedResources } from '@/components/site/resource-pagination';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';

type CatalogTaxonomy = {
    type: 'category' | 'platform' | 'language' | 'tag';
    name: string;
    value: string;
};

type Props = {
    resources: PaginatedResources;
    filters: ResourceFilters;
    filterOptions: FilterOptions;
    /** Visible H1 — matches SEO title intent for catalog / taxonomy pages. */
    heading?: string;
    /** Visible H2 above the result grid. */
    resultsHeading?: string;
    taxonomy?: CatalogTaxonomy | null;
    pageSeo?: PageSeoData | null;
};

export default function ResourcesIndex({
    resources,
    filters,
    filterOptions,
    heading = 'Hentai Games & Eroge Downloads',
    resultsHeading = 'All games',
    taxonomy = null,
    pageSeo,
}: Props) {
    const [isPending, setIsPending] = useState(false);
    // Draft search text relative to the last server `filters.q` snapshot.
    // When the server filter changes (clear / sort / external visit), prefer
    // the server value without an Effect setState.
    const [searchDraft, setSearchDraft] = useState({
        source: filters.q,
        value: filters.q,
    });
    const searchQuery =
        searchDraft.source === filters.q ? searchDraft.value : filters.q;

    const selectedTagNames = useMemo(() => {
        const bySlug = new Map(
            filterOptions.tags.map((tag) => [tag.slug, tag.name]),
        );

        return filters.tags.map((slug) => ({
            slug,
            name: bySlug.get(slug) ?? slug,
        }));
    }, [filterOptions.tags, filters.tags]);

    const hasActiveFilters =
        filters.q.trim() !== '' ||
        Boolean(filters.category) ||
        Boolean(filters.platform) ||
        Boolean(filters.language) ||
        filters.tags.length > 0;

    const applyFilters = (next: ResourceFilters) => {
        setIsPending(true);
        visitFilters(next, {
            onFinish: () => setIsPending(false),
        });
    };

    const setSearchQuery = (value: string) => {
        setSearchDraft({ source: filters.q, value });
    };

    useEffect(() => {
        const trimmed = searchQuery.trim();
        const current = filters.q.trim();

        if (trimmed === current) {
            return;
        }

        const timer = window.setTimeout(() => {
            applyFilters({ ...filters, q: trimmed });
        }, 300);

        return () => window.clearTimeout(timer);
        // Intentionally depend on searchQuery only; filters are read from closure
        // when the debounced value differs from the current server filter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchQuery]);

    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title={heading} />

            <SitePageContainer className="gap-6 sm:gap-8">
                <header className="flex flex-col gap-1">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        {heading}
                    </h1>
                    {taxonomy ? (
                        <p className="text-sm text-muted-foreground">
                            Browse {taxonomy.name.toLowerCase()} titles in the
                            catalog.
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Browse, filter, and download hentai games and eroge.
                        </p>
                    )}
                </header>

                <div className="flex flex-col gap-4 rounded-md border border-border/80 bg-card p-4 sm:p-5">
                    <div className="relative">
                        <label className="sr-only" htmlFor="resource-search">
                            Search resources
                        </label>
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="resource-search"
                            value={searchQuery}
                            onChange={(event) =>
                                setSearchQuery(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'Escape' &&
                                    searchQuery !== ''
                                ) {
                                    event.preventDefault();
                                    setSearchQuery('');
                                }
                            }}
                            placeholder="Search titles, tags, developers…"
                            className={cn(
                                'h-10 border-border/70 bg-muted/45 pr-10 pl-9 shadow-none',
                                'placeholder:text-muted-foreground/70',
                                'focus-visible:bg-background',
                                'dark:border-foreground/12 dark:bg-surface-raised',
                            )}
                            autoComplete="off"
                            enterKeyHint="search"
                        />
                        {searchQuery !== '' ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="absolute top-1/2 right-1.5 size-7 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                aria-label="Clear search"
                                onClick={() => setSearchQuery('')}
                            >
                                <X className="size-4" />
                            </Button>
                        ) : null}
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <FilterMenu
                            label="Category"
                            value={filters.category}
                            allLabel="All categories"
                            options={filterOptions.categories.map(
                                (category) => ({
                                    value: category.slug,
                                    label: category.name,
                                }),
                            )}
                            onChange={(category) =>
                                applyFilters({ ...filters, category })
                            }
                        />

                        <FilterMenu
                            label="Platform"
                            value={filters.platform}
                            allLabel="All platforms"
                            options={filterOptions.platforms.map(
                                (platform) => ({
                                    value: platform.slug,
                                    label: platform.name,
                                }),
                            )}
                            onChange={(platform) =>
                                applyFilters({ ...filters, platform })
                            }
                        />

                        <FilterMenu
                            label="Language"
                            value={filters.language}
                            allLabel="All languages"
                            options={filterOptions.languages.map(
                                (language) => ({
                                    value: language.code,
                                    label: language.name,
                                }),
                            )}
                            onChange={(language) =>
                                applyFilters({ ...filters, language })
                            }
                        />

                        <TagFilterDialog
                            options={filterOptions.tags}
                            selected={filters.tags}
                            onApply={(tags) =>
                                applyFilters({ ...filters, tags })
                            }
                        />
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                            {selectedTagNames.map((tag) => (
                                <Badge
                                    key={tag.slug}
                                    variant="secondary"
                                    className="h-7 gap-1 rounded-md border border-border/70 bg-muted/55 pr-1 pl-2.5 text-xs font-medium text-foreground shadow-none"
                                >
                                    {tag.name}
                                    <button
                                        type="button"
                                        className="inline-flex size-5 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-foreground/10 hover:text-foreground"
                                        aria-label={`Remove ${tag.name}`}
                                        onClick={() =>
                                            applyFilters({
                                                ...filters,
                                                tags: filters.tags.filter(
                                                    (slug) => slug !== tag.slug,
                                                ),
                                            })
                                        }
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                </Badge>
                            ))}
                        </div>

                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className={cn(
                                    'gap-2',
                                    filterControlClassName,
                                    !hasActiveFilters &&
                                        'text-muted-foreground',
                                )}
                                disabled={!hasActiveFilters}
                                onClick={() => {
                                    setSearchQuery('');
                                    applyFilters({
                                        ...DEFAULT_FILTERS,
                                        sort: filters.sort,
                                    });
                                }}
                            >
                                <RotateCcw className="size-4 text-muted-foreground/80" />
                                Clear
                            </Button>
                            <SortMenu
                                value={filters.sort}
                                onChange={(sort) =>
                                    applyFilters({ ...filters, sort })
                                }
                            />
                        </div>
                    </div>
                </div>

                {resources.data.length > 0 ? (
                    <section
                        id="resource-results"
                        className="relative flex scroll-mt-20 flex-col gap-5"
                        aria-labelledby="resource-results-heading"
                        aria-busy={isPending || undefined}
                    >
                        <h2
                            id="resource-results-heading"
                            className="font-heading text-lg font-semibold tracking-tight text-foreground sm:text-xl"
                        >
                            {resultsHeading}
                            <span className="ml-2 text-sm font-normal text-muted-foreground tabular-nums">
                                {resources.total}
                            </span>
                        </h2>
                        {isPending ? (
                            <div className="absolute inset-0 z-10 flex items-start justify-center pt-16">
                                <Spinner
                                    className="size-8 text-muted-foreground"
                                    aria-label="Loading resources"
                                />
                            </div>
                        ) : null}
                        <div
                            className={cn(
                                'grid grid-cols-1 gap-4 transition-opacity duration-150 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
                                isPending && 'pointer-events-none opacity-50',
                            )}
                        >
                            {resources.data.map((resource, index) => (
                                <ResourceCard
                                    key={resource.id}
                                    resource={resource}
                                    priority={index < 4}
                                />
                            ))}
                        </div>

                        {!isPending ? (
                            <ResourcePagination
                                resources={resources}
                                filters={filters}
                            />
                        ) : null}
                    </section>
                ) : (
                    <section
                        id="resource-results"
                        className="scroll-mt-20"
                        aria-labelledby="resource-results-heading"
                    >
                        <h2
                            id="resource-results-heading"
                            className="sr-only"
                        >
                            {resultsHeading}
                        </h2>
                        <SiteEmptyState
                            icon={Library}
                            title="No resources match these filters"
                            description="Clear filters or try a different search, category, platform, language, or tag."
                        />
                    </section>
                )}
            </SitePageContainer>
        </SiteLayout>
    );
}
