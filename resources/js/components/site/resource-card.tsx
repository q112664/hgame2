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
};

const overlayChipClassName = cn(
    'inline-flex h-6 items-center justify-center rounded-sm px-2',
    'bg-background/90 text-xs font-medium text-foreground shadow-none',
    'ring-1 ring-border/80',
);

const languageChipClassName = cn(
    'inline-flex h-5 items-center justify-center rounded-sm px-1.5',
    'bg-background/90 font-mono text-[11px] leading-none font-medium text-foreground shadow-none',
    'ring-1 ring-border/80',
);

export function ResourceCard({ resource }: Props) {
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
                <div className="relative aspect-[16/12] overflow-hidden rounded-t-md bg-muted">
                    <img
                        src={resource.thumbnail}
                        alt={resource.title}
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />

                    <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-1.5 p-1">
                        <span className={overlayChipClassName}>
                            {abbreviateCategory(resource.category)}
                        </span>
                        <div className="flex flex-wrap justify-end gap-1.5">
                            {resource.platforms.length > 0 ? (
                                resource.platforms.map((platform) => (
                                    <Tooltip key={platform.slug}>
                                        <TooltipTrigger asChild>
                                            <span
                                                className={cn(
                                                    overlayChipClassName,
                                                    'size-6 px-0',
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
                                ))
                            ) : (
                                <span
                                    className={cn(
                                        overlayChipClassName,
                                        'size-6 px-0',
                                    )}
                                >
                                    —
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-1.5 p-1">
                        {resource.version ? (
                            <span className={languageChipClassName}>
                                {abbreviateVersion(resource.version)}
                            </span>
                        ) : (
                            <span />
                        )}
                        <div className="flex flex-wrap justify-end gap-1">
                            {resource.languages.length > 0 ? (
                                resource.languages.map((language) => (
                                    <span
                                        key={language}
                                        className={languageChipClassName}
                                    >
                                        {abbreviateLanguage(language)}
                                    </span>
                                ))
                            ) : (
                                <span className={languageChipClassName}>—</span>
                            )}
                        </div>
                    </div>
                </div>

                <CardHeader className="gap-1.5 pt-3 pb-0">
                    <CardTitle className="line-clamp-2 text-sm leading-snug">
                        {resource.title}
                    </CardTitle>
                </CardHeader>

                <CardContent className="mt-auto flex items-center justify-between gap-2 pt-1.5 pb-3 font-mono text-xs text-muted-foreground">
                    <time dateTime={resource.publishedAt ?? undefined}>
                        {resource.publishedAt
                            ? formatDate(resource.publishedAt)
                            : 'Unscheduled'}
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
