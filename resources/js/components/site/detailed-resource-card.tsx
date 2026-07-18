import { Link } from '@inertiajs/react';
import { Download, Eye, Trash2 } from 'lucide-react';
import { PlatformIcon } from '@/components/site/platform-icon';
import { Button } from '@/components/ui/button';
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
import type { GameCard } from '@/types/resources';

export type DetailedResource = GameCard & {
    hasDownloadUpdate?: boolean;
};

type Props = {
    resource: DetailedResource;
    href: string;
    isPending?: boolean;
    isRemoving?: boolean;
    disableRemove?: boolean;
    onRemove?: () => void;
};

const overlayChipClassName = cn(
    'inline-flex h-6 items-center justify-center rounded-sm px-2',
    'bg-background/90 text-xs font-medium text-foreground ring-1 ring-border/80',
);

const detailChipClassName = cn(
    'inline-flex h-6 items-center justify-center rounded-sm px-2',
    'bg-muted font-mono text-[11px] leading-none font-medium text-muted-foreground',
);

export function DetailedResourceCard({
    resource,
    href,
    isPending = false,
    isRemoving = false,
    disableRemove = false,
    onRemove,
}: Props) {
    return (
        <Card
            size="sm"
            className={cn(
                'relative h-full gap-0 rounded-md py-0',
                isRemoving && 'opacity-50',
            )}
        >
            <Link
                href={href}
                className={cn(
                    'flex h-full flex-col focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                    isPending && 'pointer-events-none',
                )}
                prefetch={!isPending}
                tabIndex={isPending ? -1 : undefined}
            >
                <div className="relative aspect-[16/10] overflow-hidden bg-muted">
                    <img
                        src={resource.thumbnail}
                        alt={resource.title}
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />

                    <div className="absolute inset-0 ring-1 ring-foreground/8 ring-inset" />

                    <div className="absolute top-2 left-2">
                        <span className={overlayChipClassName}>
                            {abbreviateCategory(resource.category)}
                        </span>
                    </div>

                    <div className="absolute inset-x-0 bottom-2 flex items-end justify-between gap-2 px-2">
                        {resource.version ? (
                            <span className={overlayChipClassName}>
                                {abbreviateVersion(resource.version)}
                            </span>
                        ) : (
                            <span />
                        )}

                        <div className="flex flex-wrap justify-end gap-1.5">
                            {resource.platforms.map((platform) => (
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
                            ))}
                        </div>
                    </div>
                </div>

                <CardHeader className="flex-1 gap-1.5 pt-3 pb-0">
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <CardTitle className="line-clamp-2 text-base leading-snug">
                            {resource.title}
                        </CardTitle>
                        {resource.hasDownloadUpdate ? (
                            <span className="inline-flex h-6 shrink-0 items-center gap-1 rounded-sm bg-info/12 px-2 text-xs font-medium text-info">
                                <Download className="size-3.5" />
                                Updated
                            </span>
                        ) : null}
                    </div>

                    {resource.subtitle ? (
                        <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                            {resource.subtitle}
                        </p>
                    ) : null}
                </CardHeader>

                <CardContent className="flex min-w-0 flex-col gap-3 pt-3 pb-3">
                    {resource.languages.length > 0 ? (
                        <div className="flex flex-wrap gap-1.5">
                            {resource.languages.map((language) => (
                                <span
                                    key={language}
                                    className={detailChipClassName}
                                >
                                    {abbreviateLanguage(language)}
                                </span>
                            ))}
                        </div>
                    ) : null}

                    <div className="mt-auto flex items-center justify-between gap-3 font-mono text-xs text-muted-foreground">
                        <time dateTime={resource.publishedAt ?? undefined}>
                            {resource.publishedAt
                                ? formatDate(resource.publishedAt)
                                : 'Unscheduled'}
                        </time>
                        <span className="inline-flex items-center gap-1">
                            <Eye className="size-3.5" />
                            {formatViews(resource.views)}
                        </span>
                    </div>
                </CardContent>
            </Link>

            {onRemove ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="secondary"
                            size="icon-sm"
                            className="absolute top-2 right-2 z-10 bg-background/90 text-muted-foreground ring-1 ring-border/80 hover:text-destructive dark:border-foreground/15 dark:bg-surface-raised/95 dark:text-foreground dark:ring-0 dark:hover:border-foreground/25 dark:hover:bg-surface-strong dark:hover:text-destructive"
                            aria-label={`Remove ${resource.title} from favorites`}
                            disabled={disableRemove}
                            onClick={onRemove}
                        >
                            <Trash2 />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Remove favorite</TooltipContent>
                </Tooltip>
            ) : null}
        </Card>
    );
}
