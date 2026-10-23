import { cn } from '@/lib/utils';

type Props = {
    /** Placeholder cards to draw; match the grid's real page size. */
    count?: number;
    className?: string;
};

/**
 * Card-shaped placeholder for the catalogue and profile grids. It mirrors the
 * ResourceCard silhouette (thumbnail block, two title lines, chip row, meta row)
 * so swapping it in for the real grid does not shift the layout.
 */
export function ResourceGridSkeleton({ count = 8, className }: Props) {
    return (
        <ul
            className={cn(
                'grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4',
                className,
            )}
            aria-hidden
        >
            {Array.from({ length: count }, (_, index) => (
                <li key={index} className="h-full">
                    <div className="flex h-full flex-col overflow-hidden rounded-md bg-card ring-1 ring-foreground/10">
                        <div className="aspect-[16/10] animate-pulse bg-muted" />
                        <div className="flex flex-1 flex-col gap-2 p-3">
                            <div className="h-4 w-4/5 animate-pulse rounded-sm bg-muted" />
                            <div className="h-4 w-3/5 animate-pulse rounded-sm bg-muted" />
                            <div className="mt-auto flex gap-1 pt-2">
                                <div className="h-5 w-10 animate-pulse rounded-sm bg-muted" />
                                <div className="h-5 w-10 animate-pulse rounded-sm bg-muted" />
                                <div className="h-5 w-8 animate-pulse rounded-sm bg-muted" />
                            </div>
                            <div className="mt-2 flex items-center justify-between">
                                <div className="h-3 w-20 animate-pulse rounded-sm bg-muted" />
                                <div className="h-3 w-10 animate-pulse rounded-sm bg-muted" />
                            </div>
                        </div>
                    </div>
                </li>
            ))}
        </ul>
    );
}
