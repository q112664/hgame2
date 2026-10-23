import { router } from '@inertiajs/react';
import { Heart, Library, Lock } from 'lucide-react';
import { useState } from 'react';
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

/** Stand-in for a section its owner keeps private: one icon, one line. */
function PrivateSection() {
    return (
        <div
            className="flex flex-col items-center gap-2 rounded-md border border-border bg-card px-4 py-12 text-center"
            role="status"
        >
            <Lock className="size-5 text-muted-foreground" aria-hidden />
            <p className="text-sm text-muted-foreground">
                This section is kept private.
            </p>
        </div>
    );
}

type FavoriteResource = GameCard & {
    hasDownloadUpdate: boolean;
};

type Props = {
    profile: ProfileUser;
    activeTab: ProfileTab;
    isOwner: boolean;
    resourcesCount: number;
    favoritesCount: number;
    canViewResources: boolean;
    canViewFavorites: boolean;
    hiddenResources: boolean;
    hiddenFavorites: boolean;
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
    canViewResources,
    canViewFavorites,
    hiddenResources,
    hiddenFavorites,
    resources,
    favorites,
    downloadUpdateCount = 0,
    pageSeo,
}: Props) {
    const [removingId, setRemovingId] = useState<string | null>(null);

    // Both sections are always listed: a private one simply shows a notice when
    // opened, which keeps the navigation stable for visitors.
    const tabs: RouteTab<ProfileTab>[] = [
        {
            value: 'resources',
            label:
                canViewResources && resourcesCount > 0
                    ? `Resources (${resourcesCount})`
                    : 'Resources',
            href: userShow.url(profile.slug),
        },
        {
            value: 'favorites',
            label:
                canViewFavorites && favoritesCount > 0
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

                    {isOwner && (hiddenResources || hiddenFavorites) ? (
                        <p className="rounded-sm border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                            {hiddenResources && hiddenFavorites
                                ? 'Your resources and favorites are hidden from other visitors.'
                                : hiddenResources
                                  ? 'Your resources are hidden from other visitors.'
                                  : 'Your favorites are hidden from other visitors.'}{' '}
                            You can change this in Settings.
                        </p>
                    ) : null}

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

                            {!canViewResources ? (
                                <PrivateSection />
                            ) : resources && resources.data.length > 0 ? (
                                <ul className="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
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
                                        onSuccess={() =>
                                            document
                                                .getElementById(
                                                    'contributed-resources',
                                                )
                                                ?.scrollIntoView({
                                                    behavior: 'smooth',
                                                    block: 'start',
                                                })
                                        }
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

                            {!canViewFavorites ? (
                                <PrivateSection />
                            ) : favorites && favorites.data.length > 0 ? (
                                <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
                                    {favorites.data.map((resource, index) => (
                                        <li
                                            key={resource.id}
                                            className="h-full"
                                        >
                                            <ResourceCard
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
                                                highlightUpdate={isOwner}
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
                                                priority={index < 4}
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
