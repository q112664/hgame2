import { Link } from '@inertiajs/react';
import { Download, Eye, X } from 'lucide-react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    overlayChipClassName,
    resourceCardMetaClassName,
    resourceCardSubtitleClassName,
    resourceCardTitleClassName,
    resourceCardUpdateBadgeClassName,
} from '@/components/site/resource-card-styles';
import { ResourceOverlayLanguageGroup } from '@/components/site/resource-overlay-language-group';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    abbreviateCategory,
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
    priority?: boolean;
};

export function DetailedResourceCard({
    resource,
    href,
    isPending = false,
    isRemoving = false,
    disableRemove = false,
    onRemove,
    priority = false,
}: Props) {
    return (
        <Card
            size="sm"
            className={cn(
                'group relative h-full gap-0 rounded-md py-0',
                isRemoving && 'opacity-60',
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
                <div className="relative aspect-[16/10] overflow-hidden rounded-t-md bg-muted">
                    <LazyThumbnail
                        src={resource.thumbnail}
                        alt={resource.title}
                        priority={priority}
                    />

                    <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-1.5">
                        <span className={overlayChipClassName}>
                            {abbreviateCategory(resource.category)}
                        </span>
                        <div
                            className={cn(
                                'flex flex-wrap justify-end gap-1',
                                onRemove && 'pr-6',
                            )}
                        >
                            {resource.platforms.length > 0
                                ? resource.platforms.map((platform) => (
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
                                : null}
                        </div>
                    </div>

                    <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-1.5">
                        {resource.version ? (
                            <span className={overlayChipClassName}>
                                {abbreviateVersion(resource.version)}
                            </span>
                        ) : (
                            <span />
                        )}
                        <ResourceOverlayLanguageGroup
                            languages={resource.languages}
                        />
                    </div>
                </div>

                <CardHeader className="flex-1 gap-1.5 pt-3 pb-0">
                    <CardTitle className={resourceCardTitleClassName}>
                        {resource.title}
                    </CardTitle>

                    {resource.hasDownloadUpdate ? (
                        <span className={resourceCardUpdateBadgeClassName}>
                            <Download className="size-3.5" />
                            Updated
                        </span>
                    ) : null}

                    {resource.subtitle ? (
                        <p className={resourceCardSubtitleClassName}>
                            {resource.subtitle}
                        </p>
                    ) : null}
                </CardHeader>

                <CardContent
                    className={cn(
                        'mt-auto flex items-center justify-between gap-2 pt-2 pb-3',
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
                </CardContent>
            </Link>

            {onRemove ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            className={cn(
                                overlayChipClassName,
                                'absolute top-1.5 right-1.5 z-10 size-5 px-0',
                                'transition-[opacity,background-color] hover:bg-black/55 hover:text-white',
                                'opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100',
                                'focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                'disabled:pointer-events-none disabled:opacity-50',
                                isRemoving && 'opacity-100',
                            )}
                            aria-label={`Remove ${resource.title} from favorites`}
                            disabled={disableRemove}
                            onClick={onRemove}
                        >
                            {isRemoving ? (
                                <Spinner className="size-3 text-white/90" />
                            ) : (
                                <X className="size-3" aria-hidden />
                            )}
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Remove favorite</TooltipContent>
                </Tooltip>
            ) : null}
        </Card>
    );
}
