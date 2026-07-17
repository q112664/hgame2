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
            <div className="bg-background">
                <RecentResourceUpdates updates={recentUpdates} />
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="border-t border-border" />
                </div>
                <LatestResources resources={resources} />
            </div>
        </SiteLayout>
    );
}
