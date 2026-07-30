import { Head, usePage } from '@inertiajs/react';
import type { HomeHeroContent } from '@/components/site/home-hero';
import { HomeHero } from '@/components/site/home-hero';
import { LatestResources } from '@/components/site/latest-resources';
import { SiteLayout } from '@/layouts/site-layout';
import type { GameCard } from '@/types/resources';

type Props = {
    hero: HomeHeroContent;
    resources: GameCard[];
};

export default function Welcome({ hero, resources }: Props) {
    const { siteTitle } = usePage().props;
    // Empty document title → layout shows only siteTitle (from admin Site settings).
    const documentTitle =
        hero.title.trim() !== '' &&
        hero.title.trim().toLowerCase() !== siteTitle.trim().toLowerCase()
            ? hero.title.trim()
            : '';

    return (
        <SiteLayout>
            <Head title={documentTitle} />
            <div className="flex flex-col gap-10 bg-background pt-6 pb-12 sm:gap-12 sm:pt-8 sm:pb-16">
                <HomeHero hero={hero} />
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
