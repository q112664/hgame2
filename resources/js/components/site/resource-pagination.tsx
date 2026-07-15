import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { filterQuery } from '@/components/site/resource-filter-controls';
import type { ResourceFilters } from '@/components/site/resource-filter-controls';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

export type PaginatedResources = {
    data: GameCard[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

export function scrollToResourceResults() {
    document.getElementById('resource-results')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

export function ResourcePagination({
    resources,
    filters,
}: {
    resources: PaginatedResources;
    filters: ResourceFilters;
}) {
    if (resources.last_page <= 1) {
        return null;
    }

    const pageLinks = resources.links.filter(
        (link) =>
            link.label !== '&laquo; Previous' &&
            link.label !== 'Next &raquo;' &&
            !link.label.includes('Previous') &&
            !link.label.includes('Next'),
    );

    const paginationLinkProps = {
        preserveState: true,
        preserveScroll: true,
        only: ['resources', 'filters'],
        onSuccess: scrollToResourceResults,
    } satisfies Pick<
        InertiaLinkProps,
        'preserveState' | 'preserveScroll' | 'only' | 'onSuccess'
    >;

    return (
        <nav
            className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
            aria-label="Pagination"
        >
            <p className="text-sm text-muted-foreground">
                {resources.from && resources.to
                    ? `Showing ${resources.from}–${resources.to} of ${resources.total}`
                    : `${resources.total} results`}
            </p>

            <div className="flex flex-wrap items-center justify-center gap-1">
                {resources.current_page > 1 ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        asChild
                    >
                        <Link
                            href={resourcesIndex.url({
                                query: filterQuery(
                                    filters,
                                    resources.current_page - 1,
                                ),
                            })}
                            {...paginationLinkProps}
                        >
                            <ChevronLeft data-icon="inline-start" />
                            Prev
                        </Link>
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        disabled
                    >
                        <ChevronLeft data-icon="inline-start" />
                        Prev
                    </Button>
                )}

                {pageLinks.map((link, index) => {
                    const label = link.label
                        .replace(/&laquo;|&raquo;/g, '')
                        .trim();

                    if (link.url === null) {
                        return (
                            <span
                                key={`ellipsis-${index}`}
                                className="inline-flex size-8 items-center justify-center text-sm text-muted-foreground"
                            >
                                …
                            </span>
                        );
                    }

                    const page = Number(label);

                    return (
                        <Button
                            key={`${label}-${index}`}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            className={cn(
                                'size-8 p-0 shadow-none',
                                !link.active && 'border-foreground/10 bg-card',
                            )}
                            asChild
                        >
                            <Link
                                href={
                                    Number.isFinite(page)
                                        ? resourcesIndex.url({
                                              query: filterQuery(filters, page),
                                          })
                                        : link.url
                                }
                                {...paginationLinkProps}
                                aria-current={link.active ? 'page' : undefined}
                            >
                                {label}
                            </Link>
                        </Button>
                    );
                })}

                {resources.current_page < resources.last_page ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        asChild
                    >
                        <Link
                            href={resourcesIndex.url({
                                query: filterQuery(
                                    filters,
                                    resources.current_page + 1,
                                ),
                            })}
                            {...paginationLinkProps}
                        >
                            Next
                            <ChevronRight data-icon="inline-end" />
                        </Link>
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        disabled
                    >
                        Next
                        <ChevronRight data-icon="inline-end" />
                    </Button>
                )}
            </div>
        </nav>
    );
}
