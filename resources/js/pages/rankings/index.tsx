import { Link } from '@inertiajs/react';
import { Eye, Flame } from 'lucide-react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SiteLayout } from '@/layouts/site-layout';
import { formatViews } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { day, month, week } from '@/routes/rankings';
import { show as resourceShow } from '@/routes/resources';

type RankingPeriodId = 'day' | 'week' | 'month';

type RankingEntry = {
    slug: string;
    title: string;
    subtitle: string | null;
    thumbnail: string;
    thumbnailFallback: string;
    developer: string;
    category: string;
    views: number;
    downloads: number;
    rank: number;
};

type Props = {
    period: RankingPeriodId;
    entries: RankingEntry[];
    pageSeo?: PageSeoData | null;
};

const periods: { id: RankingPeriodId; label: string; href: string }[] = [
    { id: 'day', label: 'Today', href: day.url() },
    { id: 'week', label: '7 days', href: week.url() },
    { id: 'month', label: '30 days', href: month.url() },
];

function rankBadgeClassName(rank: number): string {
    if (rank === 1) {
        return 'bg-primary text-primary-foreground';
    }

    if (rank === 2) {
        return 'bg-foreground/85 text-background';
    }

    if (rank === 3) {
        return 'bg-warning/90 text-warning-foreground';
    }

    return 'bg-surface-inverse text-surface-inverse-foreground';
}

export default function RankingsIndex({ period, entries, pageSeo }: Props) {
    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title="Rankings" />

            <SitePageContainer className="gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 flex-col gap-2">
                        <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Rankings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Ranked by views plus downloads in this period.
                        </p>
                    </div>

                    <nav
                        aria-label="Ranking period"
                        className="flex w-full gap-1 rounded-lg border border-border bg-card p-1 sm:w-auto"
                    >
                        {periods.map((item) => {
                            const active = item.id === period;

                            return (
                                <Link
                                    key={item.id}
                                    href={item.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex h-8 flex-1 items-center justify-center rounded-md px-3 text-sm font-medium whitespace-nowrap',
                                        'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                        active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </header>

                {entries.length === 0 ? (
                    <SiteEmptyState
                        icon={Flame}
                        title="No traffic in this period yet"
                        description="Views and downloads are counted per day from Sep 29, 2026. Earlier lifetime totals are not included."
                    />
                ) : (
                    <ol className="flex min-w-0 flex-col gap-2">
                        {entries.map((entry) => (
                            <li key={entry.slug} className="min-w-0">
                                <Link
                                    href={resourceShow(entry.slug)}
                                    prefetch
                                    className={cn(
                                        'group grid w-full min-w-0 grid-cols-[7rem_minmax(0,1fr)_auto] items-center gap-3 rounded-lg border border-border bg-card px-3 py-3',
                                        'sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:gap-4 sm:px-4',
                                        'transition-[border-color] duration-150 hover:border-foreground/15',
                                        'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                    )}
                                >
                                    <span className="relative aspect-[16/10] w-28 shrink-0 overflow-hidden rounded-md bg-muted ring-1 ring-border/70 sm:w-36">
                                        <LazyThumbnail
                                            src={entry.thumbnail}
                                            fallbackSrc={
                                                entry.thumbnailFallback
                                            }
                                            alt=""
                                            priority={entry.rank <= 3}
                                        />
                                        <span
                                            className={cn(
                                                'absolute top-1.5 left-1.5 z-10 inline-flex size-6 items-center justify-center rounded-md text-xs font-bold tabular-nums shadow-sm sm:size-7',
                                                rankBadgeClassName(entry.rank),
                                            )}
                                            aria-label={`Rank ${entry.rank}`}
                                        >
                                            {entry.rank}
                                        </span>
                                    </span>

                                    <span className="flex min-w-0 flex-col gap-1 overflow-hidden">
                                        <span className="truncate font-heading text-base font-semibold tracking-tight text-foreground">
                                            {entry.title}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {entry.developer}
                                            <span aria-hidden> · </span>
                                            {entry.category}
                                        </span>
                                    </span>

                                    <RankingViews value={entry.views} />
                                </Link>
                            </li>
                        ))}
                    </ol>
                )}
            </SitePageContainer>
        </SiteLayout>
    );
}

function RankingViews({ value }: { value: number }) {
    return (
        <span
            className="inline-flex shrink-0 items-center gap-1 text-sm text-muted-foreground tabular-nums"
            title="Views"
        >
            <Eye className="size-3.5 shrink-0 opacity-80" aria-hidden />
            <span className="sr-only">Views</span>
            {formatViews(value)}
        </span>
    );
}
