import { router } from '@inertiajs/react';
import { Heart, Library } from 'lucide-react';
import { useState } from 'react';
import { FavoriteResourceCard } from '@/components/site/favorite-resource-card';
import { FavoritesPagination } from '@/components/site/favorites-pagination';
import type { PaginatedFavorites } from '@/components/site/favorites-pagination';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { ResourceCard } from '@/components/site/resource-card';
import { RouteTabs } from '@/components/site/route-tabs';
import type { RouteTab } from '@/components/site/route-tabs';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { UserAvatar } from '@/components/user-avatar';
import { SiteLayout } from '@/layouts/site-layout';
import { resourceTabHref } from '@/lib/resource-tabs';
import { show as resourceDetails } from '@/routes/resources';
import { destroy as destroyFavorite } from '@/routes/resources/favorite';
import { favorites as userFavorites, show as userShow } from '@/routes/users';
import type { GameCard } from '@/types/resources';

type ProfileUser = {
    slug: string;
    name: string;
    avatar: string | null;
};

type ProfileTab = 'resources' | 'favorites';

type FavoriteResource = GameCard & {
    hasDownloadUpdate: boolean;
};

type Props = {
    profile: ProfileUser;
    activeTab: ProfileTab;
    isOwner: boolean;
    resourcesCount: number;
    favoritesCount: number;
    resources: PaginatedData<GameCard> | null;
    favorites: PaginatedFavorites<FavoriteResource> | null;
    downloadUpdateCount?: number;
    pageSeo?: PageSeoData | null;
};

export default function UserProfileShow({
    profile,
    activeTab,
    isOwner,
    resourcesCount,
    favoritesCount,
    resources,
    favorites,
    downloadUpdateCount = 0,
    pageSeo,
}: Props) {
    const [removingId, setRemovingId] = useState<string | null>(null);

    const tabs: RouteTab<ProfileTab>[] = [
        {
            value: 'resources',
            label:
                resourcesCount > 0
                    ? `Resources (${resourcesCount})`
                    : 'Resources',
            href: userShow.url(profile.slug),
        },
        {
            value: 'favorites',
            label:
                favoritesCount > 0
                    ? `Favorites (${favoritesCount})`
                    : 'Favorites',
            href: userFavorites.url(profile.slug),
        },
    ];

    const removeFavorite = (resourceId: string) => {
        if (removingId !== null) {
            return;
        }

        setRemovingId(resourceId);

        router.delete(destroyFavorite(resourceId).url, {
            preserveScroll: true,
            preserveState: true,
            only: ['favorites', 'favoritesCount', 'downloadUpdateCount'],
            onFinish: () => setRemovingId(null),
        });
    };

    return (
        <SiteLayout>
            <PageSeo
                seo={pageSeo}
                title={
                    activeTab === 'favorites'
                        ? `${profile.name} · Favorites`
                        : profile.name
                }
            />

            <SitePageContainer className="gap-8">
                <header className="flex items-center gap-4 sm:gap-5">
                    <UserAvatar
                        user={profile}
                        className="size-16 sm:size-20"
                        fallbackClassName="bg-muted text-lg text-muted-foreground sm:text-xl"
                    />
                    <div className="min-w-0 space-y-1">
                        <h1 className="truncate font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            {profile.name}
                        </h1>
                    </div>
                </header>

                <div className="flex flex-col gap-6">
                    <RouteTabs
                        tabs={tabs}
                        activeValue={activeTab}
                        ariaLabel="Profile sections"
                    />

                    {activeTab === 'resources' ? (
                        <section
                            id="contributed-resources"
                            className="scroll-mt-20"
                            aria-labelledby="contributed-resources-heading"
                        >
                            <h2
                                id="contributed-resources-heading"
                                className="sr-only"
                            >
                                Resources
                            </h2>

                            {resources && resources.data.length > 0 ? (
                                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                                    {resources.data.map((resource, index) => (
                                        <li
                                            key={resource.id}
                                            className="h-full"
                                        >
                                            <ResourceCard
                                                resource={resource}
                                                priority={index < 4}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <SiteEmptyState
                                    icon={Library}
                                    title="No resources yet"
                                    description="This user has not contributed any published download packages."
                                />
                            )}

                            {resources && resources.last_page > 1 ? (
                                <div className="mt-8">
                                    <SitePagination
                                        pagination={resources}
                                        pageUrl={(page) =>
                                            userShow.url(profile.slug, {
                                                query: { page },
                                            })
                                        }
                                        ariaLabel="Contributed resources pagination"
                                        only={['resources', 'resourcesCount']}
                                    />
                                </div>
                            ) : null}
                        </section>
                    ) : (
                        <section
                            id="favorite-results"
                            className="scroll-mt-20"
                            aria-labelledby="profile-favorites-heading"
                        >
                            <h2
                                id="profile-favorites-heading"
                                className="sr-only"
                            >
                                Favorites
                            </h2>

                            {isOwner && downloadUpdateCount > 0 ? (
                                <p className="mb-6 rounded-sm border border-info/25 bg-info/10 px-4 py-2.5 text-sm text-info">
                                    {downloadUpdateCount === 1
                                        ? '1 favorite has updated downloads'
                                        : `${downloadUpdateCount} favorites have updated downloads`}
                                </p>
                            ) : null}

                            {favorites && favorites.data.length > 0 ? (
                                <ul className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                                    {favorites.data.map((resource, index) => (
                                        <li
                                            key={resource.id}
                                            className="h-full"
                                        >
                                            <FavoriteResourceCard
                                                resource={resource}
                                                href={
                                                    resource.hasDownloadUpdate
                                                        ? resourceTabHref(
                                                              resource.id,
                                                              'downloads',
                                                          )
                                                        : resourceDetails(
                                                              resource.id,
                                                          ).url
                                                }
                                                isRemoving={
                                                    removingId === resource.id
                                                }
                                                disableRemove={
                                                    removingId !== null
                                                }
                                                onRemove={
                                                    isOwner
                                                        ? () =>
                                                              removeFavorite(
                                                                  resource.id,
                                                              )
                                                        : undefined
                                                }
                                                priority={index < 2}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <SiteEmptyState
                                    icon={Heart}
                                    title={
                                        favorites && favorites.total > 0
                                            ? 'No favorites on this page'
                                            : 'No favorites yet'
                                    }
                                    description={
                                        favorites && favorites.total > 0
                                            ? 'Try another page of this favorites list.'
                                            : isOwner
                                              ? 'Save resources from detail pages to find them here later.'
                                              : 'This user has not favorited any published resources.'
                                    }
                                />
                            )}

                            {favorites ? (
                                <div className="mt-8">
                                    <FavoritesPagination
                                        resources={favorites}
                                        userId={profile.slug}
                                    />
                                </div>
                            ) : null}
                        </section>
                    )}
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
