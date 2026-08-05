import { filterQuery } from '@/components/site/resource-filter-controls';
import type { ResourceFilters } from '@/components/site/resource-filter-controls';
import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

export type PaginatedResources = PaginatedData<GameCard>;

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
    const pageUrl = (page: number) =>
        resourcesIndex.url({
            query: filterQuery(filters, page),
        });

    return (
        <SitePagination
            pagination={resources}
            pageUrl={pageUrl}
            only={['resources', 'filters', 'pageSeo']}
            onSuccess={scrollToResourceResults}
        />
    );
}
