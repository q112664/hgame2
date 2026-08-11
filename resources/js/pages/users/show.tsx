import { Library } from 'lucide-react';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { ResourceCard } from '@/components/site/resource-card';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { UserAvatar } from '@/components/user-avatar';
import { SiteLayout } from '@/layouts/site-layout';
import { show as userShow } from '@/routes/users';
import type { GameCard } from '@/types/resources';

type ProfileUser = {
    id: number;
    name: string;
    avatar: string | null;
};

type Props = {
    profile: ProfileUser;
    resources: PaginatedData<GameCard>;
    pageSeo?: PageSeoData | null;
};

export default function UserProfileShow({
    profile,
    resources,
    pageSeo,
}: Props) {
    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title={profile.name} />

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
                        <p className="text-sm text-muted-foreground">
                            {resources.total === 1
                                ? '1 contributed resource'
                                : `${resources.total} contributed resources`}
                        </p>
                    </div>
                </header>

                <section
                    id="contributed-resources"
                    className="scroll-mt-20"
                    aria-labelledby="contributed-resources-heading"
                >
                    <h2
                        id="contributed-resources-heading"
                        className="mb-4 font-heading text-base font-semibold tracking-tight text-foreground sm:text-lg"
                    >
                        Resources
                    </h2>

                    {resources.data.length > 0 ? (
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                            {resources.data.map((resource, index) => (
                                <li key={resource.id} className="h-full">
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

                    {resources.last_page > 1 ? (
                        <div className="mt-8">
                            <SitePagination
                                pagination={resources}
                                pageUrl={(page) =>
                                    userShow.url(profile.id, {
                                        query: { page },
                                    })
                                }
                                ariaLabel="Contributed resources pagination"
                                only={['resources']}
                            />
                        </div>
                    ) : null}
                </section>
            </SitePageContainer>
        </SiteLayout>
    );
}
