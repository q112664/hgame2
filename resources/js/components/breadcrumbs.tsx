import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * One row, always. Below `sm` the middle steps collapse into an ellipsis, so a
 * long trail (Home › Games › Category › Title) keeps the current page on the same
 * line instead of wrapping; the page title truncates when it still overflows.
 */
export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const collapsesOnMobile = breadcrumbs.length > 2;

    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList className="flex-nowrap">
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;
                            const isMiddle = index > 0 && !isLast;

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem
                                        className={cn(
                                            isLast && 'min-w-0',
                                            // Middle steps are desktop-only; mobile
                                            // reaches the current page in one hop.
                                            isMiddle && 'hidden sm:inline-flex',
                                        )}
                                    >
                                        {isLast ? (
                                            <BreadcrumbPage className="min-w-0 truncate">
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && (
                                        <BreadcrumbSeparator
                                            className={cn(
                                                isMiddle && 'hidden sm:block',
                                            )}
                                        />
                                    )}
                                    {index === 0 && collapsesOnMobile ? (
                                        <>
                                            <BreadcrumbItem className="sm:hidden">
                                                <span aria-hidden>…</span>
                                                <span className="sr-only">
                                                    More
                                                </span>
                                            </BreadcrumbItem>
                                            <BreadcrumbSeparator className="sm:hidden" />
                                        </>
                                    ) : null}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
