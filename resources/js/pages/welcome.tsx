import { Head } from '@inertiajs/react';
import { HomeHero } from '@/components/site/home-hero';
import { LatestResources } from '@/components/site/latest-resources';
import { SiteLayout } from '@/layouts/site-layout';
import type { GameCard } from '@/types/resources';

type Props = {
    heroBackgroundUrl: string;
    recentReleases: GameCard[];
    resources: GameCard[];
};

export default function Welcome({
    heroBackgroundUrl,
    recentReleases,
    resources,
}: Props) {
    return (
        <SiteLayout>
            <Head title="hgame - Galgame Resource Downloads" />
            <div className="flex flex-col gap-10 bg-background pt-6 pb-12 sm:gap-12 sm:pt-8 sm:pb-16">
                <HomeHero backgroundUrl={heroBackgroundUrl} />
                <LatestResources
                    id="recent-releases"
                    title="Recent releases"
                    resources={recentReleases}
                    dateField="releaseDate"
                    emptyMessage="No recent releases yet."
                    viewAllHref={null}
                />
                <LatestResources
                    id="latest"
                    title="New"
                    resources={resources}
                    dateField="publishedAt"
                    emptyMessage="No resources yet."
                />
            </div>
        </SiteLayout>
    );
}
