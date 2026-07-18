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

const overlayChipClassName = cn(
    'inline-flex h-5 max-w-full items-center justify-center rounded-sm px-1.5',
    'bg-black/40 text-[11px] leading-none font-medium text-white/90',
    'ring-1 ring-white/15 backdrop-blur-[2px]',
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
            className="h-full gap-0 rounded-md py-0 transition-[ring-color] duration-150 hover:ring-foreground/12"
        >
            <Link
                href={resourceDetails(resource.id)}
                className="group flex h-full flex-col"
                prefetch
            >
                <div className="relative aspect-[16/10] overflow-hidden rounded-t-md bg-muted">
                    <img
                        src={resource.thumbnail}
                        alt={resource.title}
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />

                    <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-1.5">
                        <span className={overlayChipClassName}>
                            {abbreviateCategory(resource.category)}
                        </span>
                        <div className="flex flex-wrap justify-end gap-1">
                            {resource.platforms.length > 0 ? (
                                resource.platforms.map((platform) => (
                                    <Tooltip key={platform.slug}>
                                        <TooltipTrigger asChild>
                                            <span
                                                className={cn(
                                                    overlayChipClassName,
                                                    'size-5 px-0',
                                                )}
                                                aria-label={platform.name}
                                            >
                                                <PlatformIcon
                                                    slug={platform.slug}
                                                    className="size-3"
                                                />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent side="bottom">
                                            {platform.name}
                                        </TooltipContent>
                                    </Tooltip>
                                ))
                            ) : null}
                        </div>
                    </div>

                    <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-1.5">
                        {resource.version ? (
                            <span
                                className={cn(
                                    overlayChipClassName,
                                    'font-mono',
                                )}
                            >
                                {abbreviateVersion(resource.version)}
                            </span>
                        ) : (
                            <span />
                        )}
                        <div className="flex flex-wrap justify-end gap-1">
                            {resource.languages.map((language) => (
                                <span
                                    key={language}
                                    className={overlayChipClassName}
                                >
                                    {abbreviateLanguage(language)}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>

                <CardHeader className="gap-1 pt-2.5 pb-0">
                    <CardTitle className="line-clamp-2 text-sm leading-snug font-medium text-foreground/85">
                        {resource.title}
                    </CardTitle>
                </CardHeader>

                <CardContent className="mt-auto flex items-center justify-between gap-2 pt-1.5 pb-2.5 text-[11px] text-muted-foreground/80">
                    <time dateTime={displayDate ?? undefined}>
                        {displayDate ? formatDate(displayDate) : 'Unscheduled'}
                    </time>
                    <span className="inline-flex items-center gap-1">
                        <Eye className="size-3" />
                        {formatViews(resource.views)}
                    </span>
                </CardContent>
            </Link>
        </Card>
    );
}
