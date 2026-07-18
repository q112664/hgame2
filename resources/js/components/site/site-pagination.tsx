import { Link, router } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedData<T = unknown> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

type Props<T> = {
    pagination: PaginatedData<T>;
    pageUrl: (page: number) => string;
    ariaLabel?: string;
    itemLabel?: string;
    only?: InertiaLinkProps['only'];
    onSuccess?: InertiaLinkProps['onSuccess'];
};

type PageJumpProps = {
    currentPage: number;
    lastPage: number;
    pageUrl: (page: number) => string;
    only?: InertiaLinkProps['only'];
    onSuccess?: InertiaLinkProps['onSuccess'];
};

function PageJump({
    currentPage,
    lastPage,
    pageUrl,
    only,
    onSuccess,
}: PageJumpProps) {
    const pageInputId = useId();
    const [pageInput, setPageInput] = useState(String(currentPage));
    const [isJumping, setIsJumping] = useState(false);
    const requestedPage = Number(pageInput);
    const targetPage =
        pageInput.trim() !== '' &&
        Number.isInteger(requestedPage) &&
        requestedPage >= 1 &&
        requestedPage <= lastPage
            ? requestedPage
            : null;
    const canJump =
        targetPage !== null && targetPage !== currentPage && !isJumping;

    const jumpToPage = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canJump || targetPage === null) {
            return;
        }

        router.visit(pageUrl(targetPage), {
            preserveState: true,
            preserveScroll: true,
            only,
            onStart: () => setIsJumping(true),
            onSuccess,
            onFinish: () => setIsJumping(false),
        });
    };

    return (
        <form
            className="flex h-8 items-center gap-1.5 sm:ml-2 sm:border-l sm:border-border sm:pl-3"
            aria-label="Jump to page"
            onSubmit={jumpToPage}
        >
            <label
                htmlFor={pageInputId}
                className="text-xs text-muted-foreground"
            >
                Page
            </label>
            <Input
                id={pageInputId}
                type="number"
                min={1}
                max={lastPage}
                step={1}
                inputMode="numeric"
                value={pageInput}
                aria-invalid={pageInput.trim() !== '' && targetPage === null}
                aria-label={`Page number, 1 to ${lastPage}`}
                className="h-8 w-16 bg-card px-1.5 text-center font-mono text-sm"
                onChange={(event) => setPageInput(event.target.value)}
                onFocus={(event) => event.currentTarget.select()}
                onBlur={() => {
                    if (targetPage === null) {
                        setPageInput(String(currentPage));
                    }
                }}
            />
            <span className="text-xs whitespace-nowrap text-muted-foreground">
                / {lastPage}
            </span>
            <Button
                variant="outline"
                size="icon-sm"
                type="submit"
                className="border-border bg-card shadow-none"
                aria-label="Go to page"
                title="Go to page"
                disabled={!canJump}
            >
                <ArrowRight />
            </Button>
        </form>
    );
}

export function SitePagination<T>({
    pagination,
    pageUrl,
    ariaLabel = 'Pagination',
    itemLabel = 'results',
    only,
    onSuccess,
}: Props<T>) {
    if (pagination.last_page <= 1) {
        return null;
    }

    const pageLinks = pagination.links.filter((link) => {
        const label = link.label.toLowerCase();

        return !label.includes('previous') && !label.includes('next');
    });

    const paginationLinkProps = {
        preserveState: true,
        preserveScroll: true,
        only,
        onSuccess,
    } satisfies Pick<
        InertiaLinkProps,
        'preserveState' | 'preserveScroll' | 'only' | 'onSuccess'
    >;

    return (
        <nav
            className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
            aria-label={ariaLabel}
        >
            <p className="text-sm text-muted-foreground">
                {pagination.from !== null && pagination.to !== null
                    ? `Showing ${pagination.from}–${pagination.to} of ${pagination.total}`
                    : `${pagination.total} ${itemLabel}`}
            </p>

            <div className="flex flex-col items-center gap-2 sm:flex-row sm:justify-end">
                <div className="flex flex-wrap items-center justify-center gap-1">
                    {pagination.current_page > 1 ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 border-border bg-card shadow-none"
                            asChild
                        >
                            <Link
                                href={pageUrl(pagination.current_page - 1)}
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
                            className="h-8 border-border bg-card shadow-none"
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
                        const page = Number(label);

                        if (link.url === null || !Number.isFinite(page)) {
                            return (
                                <span
                                    key={`ellipsis-${index}`}
                                    className="inline-flex size-8 items-center justify-center text-sm text-muted-foreground"
                                >
                                    …
                                </span>
                            );
                        }

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
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                >
                                    {label}
                                </Link>
                            </Button>
                        );
                    })}

                    {pagination.current_page < pagination.last_page ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 border-border bg-card shadow-none"
                            asChild
                        >
                            <Link
                                href={pageUrl(pagination.current_page + 1)}
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
                            className="h-8 border-border bg-card shadow-none"
                            disabled
                        >
                            Next
                            <ChevronRight data-icon="inline-end" />
                        </Button>
                    )}
                </div>

                <PageJump
                    key={pagination.current_page}
                    currentPage={pagination.current_page}
                    lastPage={pagination.last_page}
                    pageUrl={pageUrl}
                    only={only}
                    onSuccess={onSuccess}
                />
            </div>
        </nav>
    );
}
