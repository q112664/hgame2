import { LatestResources } from '@/components/site/latest-resources';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { PopularResources } from '@/components/site/popular-resources';
import type { PopularCard } from '@/components/site/popular-resources';
import { SiteLayout } from '@/layouts/site-layout';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    popular: PopularCard[];
    resources: GameCard[];
    hasMoreResources: boolean;
    pageSeo?: PageSeoData | null;
};

export default function Welcome({
    popular = [],
    resources,
    hasMoreResources = false,
    pageSeo,
}: Props) {
    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} />
            <div className="flex flex-col gap-8 bg-background pt-5 pb-10 sm:gap-10 sm:pt-6 sm:pb-14">
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
