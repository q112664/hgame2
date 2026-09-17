import { Link } from '@inertiajs/react';
import { Eye, RefreshCw } from 'lucide-react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    overlayChipClassName,
    resourceCardMetaClassName,
    resourceCardTitleClassName,
} from '@/components/site/resource-card-styles';
import { ResourceOverlayLanguageGroup } from '@/components/site/resource-overlay-language-group';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    abbreviateCategory,
    abbreviateVersion,
    formatCompactDate,
    formatDate,
    formatReleaseDate,
    formatViews,
} from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { show as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resource: GameCard;
    /** Which date to show in the card footer. Defaults to site publish time. */
    dateField?: 'publishedAt' | 'releaseDate' | 'downloadsUpdatedAt';
    /** Eager-load for above-the-fold cards (e.g. first grid row). */
    priority?: boolean;
    /** Open the resource detail page in a new tab. */
    openInNewWindow?: boolean;
};

export function ResourceCard({
    resource,
    dateField = 'publishedAt',
    priority = false,
    openInNewWindow = false,
}: Props) {
    const displayDate =
        dateField === 'releaseDate'
            ? (resource.releaseDate ?? resource.publishedAt)
            : dateField === 'downloadsUpdatedAt'
              ? (resource.downloadsUpdatedAt ?? resource.publishedAt)
              : resource.publishedAt;
    const formattedDate = displayDate
        ? dateField === 'releaseDate' && resource.releaseDate
            ? formatReleaseDate(displayDate)
            : formatDate(displayDate)
        : null;

    // Packages replaced after the resource was listed here. The listed date is
    // already on screen, so the badge only adds the part that is new.
    const updatedAt = resource.downloadsUpdatedAt;
    /** `updated` ordering puts the download update date in the primary slot. */
    const showsUpdateDate =
        dateField === 'downloadsUpdatedAt' && updatedAt !== null;
    const hasLaterUpdate =
        !showsUpdateDate &&
        updatedAt !== null &&
        (resource.publishedAt === null || updatedAt > resource.publishedAt);

    return (
        <Card
            size="sm"
            className="h-full gap-0 rounded-md py-0 transition-[ring-color] duration-150 hover:ring-foreground/12"
        >
            <Link
                href={resourceDetails(resource.id)}
                className="group flex h-full flex-col"
                prefetch={!openInNewWindow}
                target={openInNewWindow ? '_blank' : undefined}
                rel={openInNewWindow ? 'noopener noreferrer' : undefined}
            >
                <div className="relative aspect-[16/10] overflow-hidden rounded-t-md bg-muted">
                    <LazyThumbnail
                        src={resource.thumbnail}
                        fallbackSrc={resource.thumbnailFallback}
                        alt={resource.title}
                        priority={priority}
                    />

                    <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-1.5">
                        <span className={overlayChipClassName}>
                            {abbreviateCategory(resource.category)}
                        </span>
                        <div className="flex flex-wrap justify-end gap-1">
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
                        <div className="flex min-w-0 flex-wrap items-center gap-1">
                            {resource.version ? (
                                <span className={overlayChipClassName}>
                                    {abbreviateVersion(resource.version)}
                                </span>
                            ) : null}
                            {hasLaterUpdate && updatedAt ? (
                                <span
                                    className={cn(
                                        overlayChipClassName,
                                        // Accent tint marks it as a status, not
                                        // another taxonomy chip.
                                        'gap-1 bg-info/85 text-info-foreground ring-info/30',
                                    )}
                                    title={`Downloads last updated ${formatDate(updatedAt)}`}
                                >
                                    <RefreshCw
                                        className="size-3 shrink-0"
                                        aria-hidden
                                    />
                                    {formatCompactDate(updatedAt)}
                                    <span className="sr-only">
                                        Downloads last updated
                                    </span>
                                </span>
                            ) : null}
                        </div>
                        <ResourceOverlayLanguageGroup
                            languages={resource.languages}
                        />
                    </div>
                </div>

                <CardHeader className="gap-1.5 pt-3 pb-0">
                    <CardTitle className={resourceCardTitleClassName}>
                        {resource.title}
                    </CardTitle>
                </CardHeader>

                <CardContent
                    className={cn(
                        'mt-auto flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-2 pb-3',
                        resourceCardMetaClassName,
                    )}
                >
                    <span className="inline-flex min-w-0 items-center gap-1.5">
                        {showsUpdateDate ? (
                            <span
                                className="inline-flex shrink-0 items-center text-info/90"
                                title="Downloads last updated"
                            >
                                <RefreshCw className="size-3" aria-hidden />
                                <span className="sr-only">
                                    Downloads last updated
                                </span>
                            </span>
                        ) : null}
                        <time dateTime={displayDate ?? undefined}>
                            {formattedDate ?? 'Unscheduled'}
                        </time>
                    </span>
                    <span className="inline-flex shrink-0 items-center gap-1">
                        <Eye className="size-3.5 shrink-0 opacity-70" />
                        {formatViews(resource.views)}
                    </span>
                </CardContent>
            </Link>
        </Card>
    );
}
