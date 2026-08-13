import { usePage } from '@inertiajs/react';
import type { HomeHeroContent } from '@/components/site/home-hero';
import { HomeHero } from '@/components/site/home-hero';
import { LatestResources } from '@/components/site/latest-resources';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { PopularResources } from '@/components/site/popular-resources';
import { SiteLayout } from '@/layouts/site-layout';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    hero: HomeHeroContent;
    popular: GameCard[];
    resources: GameCard[];
    hasMoreResources: boolean;
    pageSeo?: PageSeoData | null;
};

export default function Welcome({
    hero,
    popular = [],
    resources,
    hasMoreResources = false,
    pageSeo,
}: Props) {
    const { siteTitle } = usePage().props;
    const heroEnabled = hero.enabled !== false;
    // Empty document title → layout shows only siteTitle (from admin Site settings).
    const documentTitle =
        heroEnabled &&
        hero.title.trim() !== '' &&
        hero.title.trim().toLowerCase() !== siteTitle.trim().toLowerCase()
            ? hero.title.trim()
            : '';

    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title={documentTitle} />
            <div className="flex flex-col gap-8 bg-background pt-5 pb-10 sm:gap-10 sm:pt-6 sm:pb-14">
                {heroEnabled ? <HomeHero hero={hero} /> : null}
                <PopularResources resources={popular} />
                <LatestResources
                    id="latest"
                    title="New"
                    resources={resources}
                    dateField="publishedAt"
                    emptyMessage="No resources yet."
                    nextPageHref={
                        hasMoreResources
                            ? resourcesIndex({ query: { page: 2 } }).url
                            : null
                    }
                />
            </div>
        </SiteLayout>
    );
}
