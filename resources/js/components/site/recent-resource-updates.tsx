import { Link } from '@inertiajs/react';
import { ChevronRight, RefreshCw, Sparkles } from 'lucide-react';
import { PlatformIcon } from '@/components/site/platform-icon';
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

function formatUpdatedAt(value: string | null): string {
    return value ? formatDate(value.slice(0, 10)) : '—';
}

function ActivityMark({
    activityType,
}: {
    activityType: GameUpdateListItem['activityType'];
}) {
    const isUpdated = activityType === 'updated';

    return (
        <span
            className={cn(
                'inline-flex h-5 shrink-0 items-center gap-1 rounded-sm px-1.5 text-[11px] leading-none font-medium ring-1',
                isUpdated
                    ? 'bg-info/10 text-info ring-info/20'
                    : 'bg-success/10 text-success ring-success/20',
            )}
        >
            {isUpdated ? (
                <RefreshCw className="size-3" aria-hidden />
            ) : (
                <Sparkles className="size-3" aria-hidden />
            )}
            {isUpdated ? 'Updated' : 'New'}
        </span>
    );
}

function UpdateRow({ update }: { update: GameUpdateListItem }) {
    return (
        <li className="min-w-0">
            <Link
                href={resourceDetails(update.id)}
                prefetch
                className={cn(
                    'group flex min-h-24 items-stretch overflow-hidden rounded-md bg-card text-card-foreground ring-1 ring-border/80 sm:min-h-28',
                    'transition-[background-color,box-shadow] duration-150',
                    'hover:bg-surface-raised hover:ring-foreground/15',
                    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                )}
            >
                <div className="w-28 shrink-0 overflow-hidden bg-muted sm:w-40">
                    <img
                        src={update.thumbnail}
                        alt=""
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />
                </div>

                <div className="flex min-w-0 flex-1 flex-col justify-center px-3.5 py-3 sm:px-4">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <ActivityMark activityType={update.activityType} />
                        <time
                            dateTime={update.updatedAt ?? undefined}
                            className="font-mono text-[11px] text-muted-foreground"
                        >
                            {formatUpdatedAt(update.updatedAt)}
                        </time>
                    </div>

                    <h3 className="mt-2 line-clamp-2 text-sm leading-snug font-medium text-foreground sm:text-[15px]">
                        {update.title}
                    </h3>

                    <div className="mt-2 flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted-foreground">
                        {update.version ? (
                            <span className="font-mono text-[11px] font-medium text-foreground/70">
                                {abbreviateVersion(update.version)}
                            </span>
                        ) : null}

                        {update.platforms.map((platform) => (
                            <Tooltip key={platform.slug}>
                                <TooltipTrigger asChild>
                                    <span
                                        className="inline-flex size-5 items-center justify-center rounded-sm bg-muted/80 text-muted-foreground"
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
                            <span
                                key={language}
                                className="font-mono text-[11px]"
                            >
                                {abbreviateLanguage(language)}
                            </span>
                        ))}
                    </div>
                </div>

                <ChevronRight
                    className="mr-3 hidden size-4 shrink-0 self-center text-muted-foreground/40 transition-[color,transform] group-hover:translate-x-0.5 group-hover:text-foreground sm:block"
                    aria-hidden
                />
            </Link>
        </li>
    );
}

export function RecentResourceUpdates({ updates }: Props) {
    return (
        <section id="updates" className="scroll-mt-16">
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
                <div className="flex items-baseline justify-between gap-4">
                    <h2 className="font-heading text-lg font-semibold tracking-tight text-foreground sm:text-xl">
                        Updates
                    </h2>
                    <Link
                        href={resourcesIndex()}
                        className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Browse all
                    </Link>
                </div>

                {updates.length > 0 ? (
                    <ul className="mt-5 grid gap-3 lg:grid-cols-2">
                        {updates.map((update) => (
                            <UpdateRow key={update.id} update={update} />
                        ))}
                    </ul>
                ) : (
                    <p className="mt-6 text-sm text-muted-foreground">
                        No updates yet.
                    </p>
                )}
            </div>
        </section>
    );
}
