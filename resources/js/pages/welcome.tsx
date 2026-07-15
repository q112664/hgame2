import { Head } from '@inertiajs/react';
import { LatestResources } from '@/components/site/latest-resources';
import { RecentResourceUpdates } from '@/components/site/recent-resource-updates';
import { SiteLayout } from '@/layouts/site-layout';
import type { GameCard, GameUpdateListItem } from '@/types/resources';

type Props = {
    resources: GameCard[];
    recentUpdates: GameUpdateListItem[];
};

export default function Welcome({ resources, recentUpdates }: Props) {
    return (
        <SiteLayout>
            <Head title="hgame - Galgame Resource Downloads" />
            <RecentResourceUpdates updates={recentUpdates} />
            <LatestResources resources={resources} />
        </SiteLayout>
    );
}
