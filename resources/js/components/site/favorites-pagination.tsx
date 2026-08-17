import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { favorites as userFavorites } from '@/routes/users';

export type PaginatedFavorites<T = unknown> = PaginatedData<T>;

function scrollToFavorites() {
    document.getElementById('favorite-results')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

export function FavoritesPagination({
    resources,
    userId,
}: {
    resources: PaginatedFavorites;
    userId: string;
}) {
    const pageUrl = (page: number) =>
        userFavorites.url(userId, {
            query: { page },
        });

    return (
        <SitePagination
            pagination={resources}
            pageUrl={pageUrl}
            ariaLabel="Favorites pagination"
            itemLabel="favorites"
            only={['favorites', 'favoritesCount', 'downloadUpdateCount']}
            onSuccess={scrollToFavorites}
        />
    );
}
