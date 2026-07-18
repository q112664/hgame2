import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { DetailedResourceCard } from '@/components/site/detailed-resource-card';
import { FavoritesPagination } from '@/components/site/favorites-pagination';
import type { PaginatedFavorites } from '@/components/site/favorites-pagination';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SiteLayout } from '@/layouts/site-layout';
import {
    details as resourceDetails,
    downloads as resourceDownloads,
} from '@/routes/resources';
import { destroy as destroyFavorite } from '@/routes/resources/favorite';
import type { GameCard } from '@/types/resources';

type FavoriteResource = GameCard & {
    hasDownloadUpdate: boolean;
};

type Props = {
    resources: PaginatedFavorites<FavoriteResource>;
    downloadUpdateCount: number;
};

export default function Favorites({ resources, downloadUpdateCount }: Props) {
    const [removingId, setRemovingId] = useState<string | null>(null);

    const removeFavorite = (resourceId: string) => {
        if (removingId !== null) {
            return;
        }

        setRemovingId(resourceId);

        router.delete(destroyFavorite(resourceId).url, {
            preserveScroll: true,
            preserveState: true,
            only: ['resources', 'downloadUpdateCount'],
            onFinish: () => setRemovingId(null),
        });
    };

    return (
        <SiteLayout>
            <Head title="Favorites" />

            <SitePageContainer className="gap-8">
                <div className="flex items-baseline justify-between gap-3">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        Favorites
                    </h1>
                    {resources.total > 0 ? (
                        <p className="text-base text-muted-foreground">
                            {resources.total}
                        </p>
                    ) : null}
                </div>

                {downloadUpdateCount > 0 ? (
                    <p className="rounded-sm border border-info/25 bg-info/10 px-4 py-2.5 text-sm text-info">
                        {downloadUpdateCount === 1
                            ? '1 favorite has updated downloads'
                            : `${downloadUpdateCount} favorites have updated downloads`}
                    </p>
                ) : null}

                <div id="favorite-results" className="scroll-mt-20">
                    {resources.data.length > 0 ? (
                        <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {resources.data.map((resource) => (
                                <li key={resource.id} className="h-full">
                                    <DetailedResourceCard
                                        resource={resource}
                                        href={
                                            resource.hasDownloadUpdate
                                                ? resourceDownloads(resource.id)
                                                      .url
                                                : resourceDetails(resource.id)
                                                      .url
                                        }
                                        isRemoving={removingId === resource.id}
                                        disableRemove={removingId !== null}
                                        onRemove={() =>
                                            removeFavorite(resource.id)
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="py-8 text-center text-base text-muted-foreground">
                            {resources.total > 0
                                ? 'No favorites on this page'
                                : 'No favorites yet'}
                        </p>
                    )}

                    <div className="mt-8">
                        <FavoritesPagination resources={resources} />
                    </div>
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
