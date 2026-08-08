import { Link } from '@inertiajs/react';
import { Download, Eye, X } from 'lucide-react';
import type { DetailedResource } from '@/components/site/detailed-resource-card';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    overlayChipClassName,
    resourceCardMetaClassName,
    resourceCardSubtitleClassName,
    resourceCardTitleClassName,
    resourceCardUpdateBadgeClassName,
} from '@/components/site/resource-card-styles';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
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

type Props = {
    resource: DetailedResource;
    href: string;
    isRemoving?: boolean;
    disableRemove?: boolean;
    onRemove?: () => void;
    priority?: boolean;
};

const metaChipClassName = cn(
    'inline-flex h-5 items-center gap-1 rounded-sm px-1.5',
    'bg-muted text-[11px] leading-none font-medium text-muted-foreground',
);

export function FavoriteResourceCard({
    resource,
    href,
    isRemoving = false,
    disableRemove = false,
    onRemove,
    priority = false,
}: Props) {
    return (
        <Card
            size="sm"
            className={cn(
                'group relative h-full gap-0 overflow-hidden rounded-md py-0',
                isRemoving && 'opacity-60',
            )}
        >
            <Link
                href={href}
                className={cn(
                    'flex h-full min-h-0 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                )}
                prefetch
            >
                <div className="relative aspect-[16/10] w-[42%] max-w-44 shrink-0 overflow-hidden bg-muted">
                    <LazyThumbnail
                        src={resource.thumbnail}
                        fallbackSrc={resource.thumbnailFallback}
                        alt={resource.title}
                        priority={priority}
                    />
                    <span
                        className={cn(
                            overlayChipClassName,
                            'absolute top-1.5 left-1.5',
                        )}
                    >
                        {abbreviateCategory(resource.category)}
                    </span>
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-2 p-3 sm:gap-2.5 sm:p-3.5">
                    <div className={cn('flex min-w-0 flex-col gap-1.5', onRemove && 'pr-7')}>
                        <h3 className={resourceCardTitleClassName}>
                            {resource.title}
                        </h3>
                        {resource.hasDownloadUpdate ? (
                            <span className={resourceCardUpdateBadgeClassName}>
                                <Download className="size-3" />
                                Updated
                            </span>
                        ) : null}
                        {resource.subtitle ? (
                            <p className={resourceCardSubtitleClassName}>
                                {resource.subtitle}
                            </p>
                        ) : null}
                    </div>

                    <div className="flex min-w-0 flex-wrap items-center gap-1">
                        {resource.version ? (
                            <span className={metaChipClassName}>
                                {abbreviateVersion(resource.version)}
                            </span>
                        ) : null}
                        {resource.platforms.map((platform) => (
                            <Tooltip key={platform.slug}>
                                <TooltipTrigger asChild>
                                    <span
                                        className={cn(
                                            metaChipClassName,
                                            'size-5 justify-center px-0',
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
                        ))}
                        {resource.languages.map((language) => (
                            <span
                                key={language.code}
                                className={metaChipClassName}
                            >
                                {abbreviateLanguage(language.name)}
                            </span>
                        ))}
                    </div>

                    <div
                        className={cn(
                            'mt-auto flex items-center justify-between gap-2 pt-0.5',
                            resourceCardMetaClassName,
                        )}
                    >
                        <time dateTime={resource.publishedAt ?? undefined}>
                            {resource.publishedAt
                                ? formatDate(resource.publishedAt)
                                : 'Unscheduled'}
                        </time>
                        <span className="inline-flex items-center gap-1">
                            <Eye className="size-3.5 shrink-0 opacity-70" />
                            {formatViews(resource.views)}
                        </span>
                    </div>
                </div>
            </Link>

            {onRemove ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            className={cn(
                                'absolute top-2.5 right-2.5 z-10 inline-flex size-7 items-center justify-center rounded-sm',
                                'text-muted-foreground transition-colors',
                                'hover:bg-muted hover:text-foreground',
                                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                'disabled:pointer-events-none disabled:opacity-50',
                            )}
                            aria-label={`Remove ${resource.title} from favorites`}
                            disabled={disableRemove}
                            onClick={onRemove}
                        >
                            {isRemoving ? (
                                <Spinner className="size-3.5" />
                            ) : (
                                <X className="size-3.5" aria-hidden />
                            )}
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Remove favorite</TooltipContent>
                </Tooltip>
            ) : null}
        </Card>
    );
}
