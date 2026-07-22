import { Head, usePage } from '@inertiajs/react';
import { HomeHero } from '@/components/site/home-hero';
import { LatestResources } from '@/components/site/latest-resources';
import { SiteLayout } from '@/layouts/site-layout';
import type { GameCard } from '@/types/resources';

type Props = {
    heroBackgroundUrl: string;
    resources: GameCard[];
};

export default function Welcome({ heroBackgroundUrl, resources }: Props) {
    const { name } = usePage().props;

    return (
        <SiteLayout>
            <Head title={`${name} - Galgame Resource Downloads`} />
            <div className="flex flex-col gap-10 bg-background pt-6 pb-12 sm:gap-12 sm:pt-8 sm:pb-16">
                <HomeHero backgroundUrl={heroBackgroundUrl} />
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
