import { Head } from '@inertiajs/react';
import { Hero } from '@/components/site/hero';
import { LatestResources } from '@/components/site/latest-resources';
import { SiteLayout } from '@/layouts/site-layout';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
};

export default function Welcome({ resources }: Props) {
    return (
        <SiteLayout>
            <Head title="hgame - Galgame Resource Downloads" />
            <div className="bg-background">
                <Hero />
                <LatestResources resources={resources} />
            </div>
        </SiteLayout>
    );
}