import { Link } from '@inertiajs/react';
import { ArrowUpRight, ChevronRight, RefreshCw, Sparkles } from 'lucide-react';
import { PlatformIcon } from '@/components/site/platform-icon';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    abbreviateLanguage,
    abbreviateVersion,
    formatDate,
} from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import {
    details as resourceDetails,
    index as resourcesIndex,
} from '@/routes/resources';
import type { GameUpdateListItem } from '@/types/resources';

type Props = {
    updates: GameUpdateListItem[];
};

const metaChipClassName = cn(
    'inline-flex h-6 items-center gap-1.5 rounded-md px-2',
    'bg-muted text-xs leading-none font-medium text-muted-foreground',
);

function formatUpdatedAt(value: string | null): string {
    return value ? formatDate(value.slice(0, 10)) : 'Date unavailable';
}

function ActivityBadge({
    activityType,
}: {
    activityType: GameUpdateListItem['activityType'];
}) {
    const isUpdated = activityType === 'updated';

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium',
                isUpdated
                    ? 'bg-sky-500/12 text-sky-700 dark:text-sky-300'
                    : 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
            )}
        >
            {isUpdated ? (
                <RefreshCw className="size-3" aria-hidden />
            ) : (
                <Sparkles className="size-3" aria-hidden />
            )}
            {isUpdated ? 'Updated' : 'Published'}
        </span>
    );
}

function UpdateRow({ update }: { update: GameUpdateListItem }) {
    return (
        <li>
            <Link
                href={resourceDetails(update.id)}
                prefetch
                className={cn(
                    'group relative flex items-center gap-3 rounded-xl p-2.5 sm:gap-5 sm:p-3',
                    'bg-background/80 ring-1 ring-foreground/8',
                    'transition-colors duration-150',
                    'hover:bg-muted/50',
                    'focus-visible:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                )}
            >
                <div className="relative aspect-[16/10] w-28 shrink-0 overflow-hidden rounded-lg bg-muted sm:w-40">
                    <img
                        src={update.thumbnail}
                        alt=""
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />
                    <div className="absolute inset-0 bg-linear-to-t from-black/25 via-transparent to-transparent opacity-70" />
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <ActivityBadge activityType={update.activityType} />
                        <time
                            dateTime={update.updatedAt ?? undefined}
                            className="text-xs text-muted-foreground"
                        >
                            {formatUpdatedAt(update.updatedAt)}
                        </time>
                    </div>

                    <h3 className="mt-1.5 line-clamp-2 text-sm font-semibold tracking-tight text-foreground sm:text-base">
                        {update.title}
                    </h3>

                    {update.subtitle ? (
                        <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground sm:text-sm">
                            {update.subtitle}
                        </p>
                    ) : (
                        <p className="mt-0.5 truncate text-xs text-muted-foreground sm:text-sm">
                            {update.developer}
                        </p>
                    )}

                    <div className="mt-2.5 flex min-w-0 flex-wrap items-center gap-1.5">
                        {update.version ? (
                            <span className={metaChipClassName}>
                                {abbreviateVersion(update.version)}
                            </span>
                        ) : null}

                        {update.platforms.map((platform) => (
                            <Tooltip key={platform.slug}>
                                <TooltipTrigger asChild>
                                    <span
                                        className={cn(
                                            metaChipClassName,
                                            'size-6 justify-center px-0',
                                        )}
                                        aria-label={platform.name}
                                    >
                                        <PlatformIcon
                                            slug={platform.slug}
                                            className="size-3.5"
                                        />
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent side="bottom">
                                    {platform.name}
                                </TooltipContent>
                            </Tooltip>
                        ))}

                        {update.languages.map((language) => (
                            <span key={language} className={metaChipClassName}>
                                {abbreviateLanguage(language)}
                            </span>
                        ))}
                    </div>
                </div>

                <ChevronRight
                    className="hidden size-5 shrink-0 text-muted-foreground/50 transition-colors group-hover:text-foreground sm:block"
                    aria-hidden
                />
            </Link>
        </li>
    );
}

export function RecentResourceUpdates({ updates }: Props) {
    return (
        <section id="updates" className="relative overflow-hidden">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,oklch(0.92_0.01_250/0.7),transparent_55%)] dark:bg-[radial-gradient(ellipse_at_top,oklch(0.28_0.02_250/0.45),transparent_55%)]"
            />

            <div className="relative mx-auto flex max-w-7xl flex-col gap-7 px-4 py-12 sm:px-6 sm:py-14 lg:px-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-3">
                        <div className="inline-flex w-fit items-center gap-2 rounded-full bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-foreground/8">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-sky-400/60 opacity-75" />
                                <span className="relative inline-flex size-2 rounded-full bg-sky-500" />
                            </span>
                            Activity feed
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <h2 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                Recent resource updates
                            </h2>
                            <p className="max-w-xl text-sm text-muted-foreground sm:text-[15px]">
                                Fresh versions, new releases, and download
                                changes as they land
                            </p>
                        </div>
                    </div>

                    <Button
                        asChild
                        variant="outline"
                        className="border-foreground/10 bg-background/80 shadow-none backdrop-blur-sm"
                    >
                        <Link href={resourcesIndex()} className="gap-1.5">
                            Browse all
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </Button>
                </div>

                {updates.length > 0 ? (
                    <ul className="flex flex-col gap-2.5 sm:gap-3">
                        {updates.map((update) => (
                            <UpdateRow key={update.id} update={update} />
                        ))}
                    </ul>
                ) : (
                    <div className="rounded-xl bg-background/70 px-6 py-12 text-center ring-1 ring-foreground/8">
                        <p className="text-sm text-muted-foreground">
                            No resource updates yet.
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
