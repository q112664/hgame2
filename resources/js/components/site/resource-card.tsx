import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { PlatformIcon } from '@/components/site/platform-icon';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    abbreviateCategory,
    abbreviateLanguage,
    abbreviateVersion,
    formatDate,
    formatViews,
} from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { details as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resource: GameCard;
    /** Which date to show in the card footer. Defaults to site publish time. */
    dateField?: 'publishedAt' | 'releaseDate';
};

const metaChipClassName = cn(
    'inline-flex h-5 max-w-full items-center justify-center rounded-sm px-1.5',
    'bg-muted text-[11px] leading-none font-medium text-muted-foreground',
);

export function ResourceCard({
    resource,
    dateField = 'publishedAt',
}: Props) {
    const displayDate =
        dateField === 'releaseDate'
            ? (resource.releaseDate ?? resource.publishedAt)
            : resource.publishedAt;

    return (
        <Card
            size="sm"
            className="h-full gap-0 rounded-md py-0 transition-[ring-color] duration-150 hover:ring-primary/25"
        >
            <Link
                href={resourceDetails(resource.id)}
                className="group flex h-full flex-col"
                prefetch
            >
                <div className="aspect-[16/10] overflow-hidden rounded-t-md bg-muted">
                    <img
                        src={resource.thumbnail}
                        alt={resource.title}
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />
                </div>

                <CardHeader className="gap-2 pt-3 pb-0">
                    <CardTitle className="line-clamp-2 text-sm leading-snug">
                        {resource.title}
                    </CardTitle>

                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <span className={metaChipClassName}>
                                {abbreviateCategory(resource.category)}
                            </span>
                            {resource.version ? (
                                <span
                                    className={cn(
                                        metaChipClassName,
                                        'shrink-0 font-mono',
                                    )}
                                >
                                    {abbreviateVersion(resource.version)}
                                </span>
                            ) : null}
                        </div>

                        {(resource.platforms.length > 0 ||
                            resource.languages.length > 0) && (
                            <div className="flex flex-wrap items-center gap-1">
                                {resource.platforms.map((platform) => (
                                    <Tooltip key={platform.slug}>
                                        <TooltipTrigger asChild>
                                            <span
                                                className={cn(
                                                    metaChipClassName,
                                                    'size-5 px-0',
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
                                {resource.languages.map((language) => (
                                    <span
                                        key={language}
                                        className={metaChipClassName}
                                    >
                                        {abbreviateLanguage(language)}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                </CardHeader>

                <CardContent className="mt-auto flex items-center justify-between gap-2 pt-2 pb-3 text-xs text-muted-foreground">
                    <time dateTime={displayDate ?? undefined}>
                        {displayDate ? formatDate(displayDate) : 'Unscheduled'}
                    </time>
                    <span className="inline-flex items-center gap-1">
                        <Eye className="size-3.5" />
                        {formatViews(resource.views)}
                    </span>
                </CardContent>
            </Link>
        </Card>
    );
}
