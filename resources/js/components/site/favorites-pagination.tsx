import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as favoritesIndex } from '@/routes/favorites';

export type PaginatedFavorites<T = unknown> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

function scrollToFavorites() {
    document.getElementById('favorite-results')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

export function FavoritesPagination({
    resources,
}: {
    resources: PaginatedFavorites;
}) {
    if (resources.last_page <= 1) {
        return null;
    }

    const pageLinks = resources.links.filter(
        (link) =>
            !link.label.toLowerCase().includes('previous') &&
            !link.label.toLowerCase().includes('next'),
    );

    const paginationLinkProps = {
        preserveState: true,
        preserveScroll: true,
        onSuccess: scrollToFavorites,
    } satisfies Pick<
        InertiaLinkProps,
        'preserveState' | 'preserveScroll' | 'onSuccess'
    >;

    const pageUrl = (page: number) =>
        favoritesIndex.url({
            query: { page },
        });

    return (
        <nav
            className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
            aria-label="Favorites pagination"
        >
            <p className="text-sm text-muted-foreground">
                {resources.from !== null && resources.to !== null
                    ? `Showing ${resources.from}-${resources.to} of ${resources.total}`
                    : `${resources.total} favorites`}
            </p>

            <div className="flex flex-wrap items-center justify-center gap-1">
                {resources.current_page > 1 ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-border bg-card shadow-none"
                        asChild
                    >
                        <Link
                            href={pageUrl(resources.current_page - 1)}
                            {...paginationLinkProps}
                        >
                            <ChevronLeft data-icon="inline-start" />
                            Prev
                        </Link>
                    </Button>
                ) : null}

                {pageLinks.map((link, index) => {
                    const label = link.label
                        .replace(/&laquo;|&raquo;/g, '')
                        .trim();

                    if (link.url === null || !/^\d+$/.test(label)) {
                        return (
                            <span
                                key={`ellipsis-${index}`}
                                className="inline-flex size-8 items-center justify-center text-sm text-muted-foreground"
                            >
                                ...
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
                                !link.active && 'border-border bg-card',
                            )}
                            asChild
                        >
                            <Link
                                href={pageUrl(page)}
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
                        className="h-8 border-border bg-card shadow-none"
                        asChild
                    >
                        <Link
                            href={pageUrl(resources.current_page + 1)}
                            {...paginationLinkProps}
                        >
                            Next
                            <ChevronRight data-icon="inline-end" />
                        </Link>
                    </Button>
                ) : null}
            </div>
        </nav>
    );
}
