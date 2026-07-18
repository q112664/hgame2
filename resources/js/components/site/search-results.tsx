import { DetailedResourceCard } from '@/components/site/detailed-resource-card';
import { Card, CardHeader } from '@/components/ui/card';
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
                'grid grid-cols-1 gap-4 transition-opacity duration-150 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
                isPending ? 'opacity-50' : 'opacity-100',
            )}
            aria-busy={isPending}
        >
            {resources.map((resource) => (
                <li key={resource.id} className="h-full">
                    <DetailedResourceCard
                        resource={resource}
                        href={resourceDetails(resource.id).url}
                        isPending={isPending}
                    />
                </li>
            ))}
        </ul>
    );
}

export function SearchResultsSkeleton() {
    return (
        <div
            className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            aria-busy="true"
            aria-label="Searching"
        >
            {Array.from({ length: 8 }, (_, index) => (
                <Card
                    key={index}
                    size="sm"
                    className="gap-0 py-0"
                    aria-hidden="true"
                >
                    <div className="aspect-[16/10] animate-pulse bg-muted" />
                    <CardHeader className="gap-2 py-4">
                        <div className="h-4 w-3/4 animate-pulse rounded-sm bg-muted" />
                        <div className="h-3 w-1/2 animate-pulse rounded-sm bg-muted" />
                    </CardHeader>
                </Card>
            ))}
        </div>
    );
}
