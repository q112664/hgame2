import { Head } from '@inertiajs/react';
import { LatestResources } from '@/components/site/latest-resources';
import { SiteHero } from '@/components/site/site-hero';
import { SiteLayout } from '@/layouts/site-layout';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
};

export default function Welcome({ resources }: Props) {
    return (
        <SiteLayout>
            <Head title="hgame - Galgame Resource Downloads" />
            <SiteHero />
            <LatestResources resources={resources} />
            <div id="categories" className="sr-only" aria-hidden />
        </SiteLayout>
    );
}
