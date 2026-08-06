import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { tags as resourcesTagsIndex } from '@/routes/resources';

const navLinkClassName = cn(
    'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium',
    'text-foreground/75 transition-[color,background-color]',
    'hover:bg-primary/10 hover:text-primary',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

const mobileRowClassName = cn(
    'flex h-10 w-full items-center justify-start rounded-md px-3 text-sm font-medium',
    'text-foreground/75 transition-colors',
    'hover:bg-primary/10 hover:text-primary',
);

function pathIsActive(currentPath: string, prefix: string): boolean {
    return currentPath === prefix || currentPath.startsWith(`${prefix}/`);
}

/** Desktop Tags index link only (genre/platform/language stay out of the bar). */
export function SiteTaxonomyNavDesktop({ className }: { className?: string }) {
    const { url } = usePage();
    const currentPath = url.split('?')[0] || '/';
    const active = pathIsActive(currentPath, '/resources/tags');

    return (
        <div className={cn('flex items-center gap-1', className)}>
            <Link
                href={resourcesTagsIndex.url()}
                className={cn(
                    navLinkClassName,
                    active &&
                        'bg-primary/12 text-primary hover:bg-primary/15 hover:text-primary',
                )}
                aria-current={active ? 'page' : undefined}
                prefetch
            >
                Tags
            </Link>
        </div>
    );
}

/** Mobile Tags index link. */
export function SiteTaxonomyNavMobile({
    onNavigate,
}: {
    onNavigate?: () => void;
}) {
    const { url } = usePage();
    const currentPath = url.split('?')[0] || '/';
    const active = pathIsActive(currentPath, '/resources/tags');

    return (
        <Link
            href={resourcesTagsIndex.url()}
            className={cn(
                mobileRowClassName,
                active && 'bg-primary/12 text-primary',
            )}
            onClick={onNavigate}
            prefetch
        >
            Tags
        </Link>
    );
}
