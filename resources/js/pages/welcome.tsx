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
    /** Resources with real download/package updates (separate from new listings). */
    updatedResources?: GameCard[];
    pageSeo?: PageSeoData | null;
};

export default function Welcome({
    hero,
    popular = [],
    resources,
    hasMoreResources = false,
    updatedResources = [],
    pageSeo,
}: Props) {
    const { siteTitle } = usePage().props;
    // Empty document title → layout shows only siteTitle (from admin Site settings).
    const documentTitle =
        hero.title.trim() !== '' &&
        hero.title.trim().toLowerCase() !== siteTitle.trim().toLowerCase()
            ? hero.title.trim()
            : '';

    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title={documentTitle} />
            <div className="flex flex-col gap-8 bg-background pt-5 pb-10 sm:gap-10 sm:pt-6 sm:pb-14">
                <HomeHero hero={hero} />
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
                {updatedResources.length > 0 ? (
                    <LatestResources
                        id="updated"
                        title="Updated"
                        resources={updatedResources}
                        dateField="downloadsUpdatedAt"
                        emptyMessage="No download updates yet."
                        viewAllHref={
                            resourcesIndex({ query: { sort: 'updated' } }).url
                        }
                    />
                ) : null}
            </div>
        </SiteLayout>
    );
}
