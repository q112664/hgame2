import { Link } from '@inertiajs/react';
import type { SearchResource } from '@/hooks/use-resource-search';
import { cn } from '@/lib/utils';
import { details as resourceDetails } from '@/routes/resources';

type Props = {
    resources: SearchResource[];
    isPending: boolean;
};

export function SearchResults({ resources, isPending }: Props) {
    return (
        <ul
            className={cn(
                'divide-y divide-foreground/8 transition-opacity duration-150',
                isPending ? 'opacity-50' : 'opacity-100',
            )}
            aria-busy={isPending}
        >
            {resources.map((resource) => (
                <li key={resource.id}>
                    <Link
                        href={resourceDetails(resource.id)}
                        className={cn(
                            'group flex items-center gap-4 py-4 sm:gap-5 sm:py-5',
                            'transition-opacity duration-150 hover:opacity-80',
                            isPending && 'pointer-events-none',
                        )}
                        prefetch={!isPending}
                        tabIndex={isPending ? -1 : undefined}
                    >
                        <div className="aspect-video w-32 shrink-0 overflow-hidden rounded-md bg-muted sm:w-40">
                            <img
                                src={resource.thumbnail}
                                alt=""
                                className="size-full object-cover"
                                loading="lazy"
                                referrerPolicy="no-referrer"
                            />
                        </div>
                        <div className="min-w-0 flex-1 space-y-1">
                            <span className="line-clamp-2 text-base leading-snug font-medium text-foreground sm:text-lg">
                                {resource.title}
                            </span>
                            {resource.subtitle ? (
                                <span className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                                    {resource.subtitle}
                                </span>
                            ) : null}
                        </div>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
