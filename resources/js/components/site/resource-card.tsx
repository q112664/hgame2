import { Link } from '@inertiajs/react';
import { Eye, RefreshCw, X } from 'lucide-react';
import { useState } from 'react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    overlayCategoryChipClassName,
    overlayChipClassName,
    overlayIconChipClassName,
    resourceCardMetaClassName,
    resourceCardTitleClassName,
} from '@/components/site/resource-card-styles';
import { ResourceOverlayLanguageGroup } from '@/components/site/resource-overlay-language-group';
import { Button } from '@/components/ui/button';
import { Card, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
    formatRelativeDate,
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
    /** Override the destination (e.g. jump straight to the downloads tab). */
    href?: string;
    /** Favorites: emphasise a download change the viewer has not seen yet. */
    highlightUpdate?: boolean;
    /** Favorites: render a remove control on the thumbnail. */
    onRemove?: () => void;
    isRemoving?: boolean;
    disableRemove?: boolean;
};

export function ResourceCard({
    resource,
    dateField = 'publishedAt',
    priority = false,
    openInNewWindow = false,
    href,
    highlightUpdate = false,
    onRemove,
    isRemoving = false,
    disableRemove = false,
}: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
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
    /** Unseen download change reported by the server (favorites only). */
    const hasUnseenUpdate =
        highlightUpdate && resource.hasDownloadUpdate === true;
    const hasLaterUpdate =
        !showsUpdateDate &&
        updatedAt !== null &&
        (resource.publishedAt === null || updatedAt > resource.publishedAt);

    return (
        <Card
            size="sm"
            className={cn(
                'relative h-full gap-0 rounded-[8px] py-0 ring-border/80 transition-[ring-color] duration-150 hover:ring-foreground/15',
                hasUnseenUpdate && 'ring-info/45 hover:ring-info/60',
                isRemoving && 'opacity-60',
            )}
        >
            <Link
                href={href ?? resourceDetails(resource.id)}
                className="group flex h-full flex-col"
                prefetch={!openInNewWindow}
                target={openInNewWindow ? '_blank' : undefined}
                rel={openInNewWindow ? 'noopener noreferrer' : undefined}
            >
                {/* 4:3 is the shape of most covers (560x420); the rest letterbox
                    onto a blurred copy of their own artwork. */}
                <div className="relative aspect-[4/3] overflow-hidden rounded-t-[8px] bg-muted">
                    <LazyThumbnail
                        src={resource.thumbnail}
                        fallbackSrc={resource.thumbnailFallback}
                        alt={resource.title}
                        priority={priority}
                        fit="contain"
                        ambient
                    />

                    {updatedAt && (hasLaterUpdate || hasUnseenUpdate) ? (
                        <span
                            className={cn(
                                overlayChipClassName,
                                // Freshness reads first: keep the status cue in
                                // the top-left, away from the taxonomy strip.
                                'absolute top-1.5 left-1.5 z-10 gap-1 bg-info/90 text-info-foreground',
                            )}
                            title={`Downloads last updated ${formatDate(updatedAt)}`}
                        >
                            <RefreshCw
                                className="size-3 shrink-0"
                                aria-hidden
                            />
                            {hasUnseenUpdate ? <span>Updated</span> : null}
                            <span suppressHydrationWarning>
                                {formatRelativeDate(updatedAt)}
                            </span>
                            <span className="sr-only">
                                Downloads last updated
                            </span>
                        </span>
                    ) : null}

                    {/* One compact strip along the bottom edge: taxonomy and
                        package on the left, languages on the right. */}
                    <div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-1 p-1.5">
                        <div className="flex min-w-0 flex-nowrap items-center gap-1">
                            <span
                                className={overlayCategoryChipClassName}
                                title={resource.category}
                            >
                                {abbreviateCategory(resource.category)}
                            </span>
                            {resource.platforms.length > 0
                                ? resource.platforms.map((platform) => (
                                      <Tooltip key={platform.slug}>
                                          <TooltipTrigger asChild>
                                              <span
                                                  className={
                                                      overlayIconChipClassName
                                                  }
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
                            {resource.version ? (
                                <span className={overlayChipClassName}>
                                    {abbreviateVersion(resource.version)}
                                </span>
                            ) : null}
                        </div>
                        <ResourceOverlayLanguageGroup
                            languages={resource.languages}
                        />
                    </div>
                </div>

                <div className="flex flex-1 flex-col px-3 py-2.5">
                    <CardTitle className={resourceCardTitleClassName}>
                        {resource.title}
                    </CardTitle>

                    <div
                        className={cn(
                            'mt-auto flex items-center justify-between gap-2 pt-1.5',
                            resourceCardMetaClassName,
                        )}
                    >
                        <span className="inline-flex min-w-0 items-center gap-[5px] whitespace-nowrap">
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
                            {/* Only download updates read as relative time; the
                            listing date stays absolute. */}
                            {showsUpdateDate && displayDate ? (
                                <time
                                    dateTime={displayDate}
                                    title={formattedDate ?? undefined}
                                    suppressHydrationWarning
                                >
                                    {formatRelativeDate(displayDate)}
                                </time>
                            ) : (
                                <time
                                    dateTime={displayDate ?? undefined}
                                    title={
                                        formattedDate
                                            ? `Added ${formattedDate}`
                                            : undefined
                                    }
                                >
                                    {formattedDate ?? 'Unscheduled'}
                                </time>
                            )}
                        </span>
                        <span className="inline-flex shrink-0 items-center gap-[5px] whitespace-nowrap">
                            <Eye className="size-3.5 shrink-0 opacity-70" />
                            {formatViews(resource.views)}
                        </span>
                    </div>
                </div>
            </Link>

            {onRemove ? (
                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogTrigger asChild>
                        <button
                            type="button"
                            className={cn(
                                'absolute top-1.5 right-1.5 z-10 inline-flex size-6 items-center justify-center rounded-sm',
                                // Rides on the artwork: needs its own surface to stay legible.
                                'bg-black/55 text-white/90 backdrop-blur-[2px] transition-colors',
                                'hover:bg-black/75 hover:text-white',
                                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                            )}
                            aria-label={`Remove ${resource.title} from favorites`}
                        >
                            {isRemoving ? (
                                <Spinner className="size-3.5" />
                            ) : (
                                <X className="size-3.5" aria-hidden />
                            )}
                        </button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Remove from favorites?</DialogTitle>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" size="sm">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                size="sm"
                                disabled={disableRemove}
                                onClick={() => {
                                    setConfirmOpen(false);
                                    onRemove();
                                }}
                            >
                                Remove
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            ) : null}
        </Card>
    );
}
