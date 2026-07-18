import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { index as favoritesIndex } from '@/routes/favorites';

export type PaginatedFavorites<T = unknown> = PaginatedData<T>;

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
    const pageUrl = (page: number) =>
        favoritesIndex.url({
            query: { page },
        });

    return (
        <SitePagination
            pagination={resources}
            pageUrl={pageUrl}
            ariaLabel="Favorites pagination"
            itemLabel="favorites"
            only={['resources', 'downloadUpdateCount']}
            onSuccess={scrollToFavorites}
        />
    );
}
