import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    ChevronUp,
    Download,
    HardDrive,
    Images,
    Info,
} from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { LightboxSlide } from '@/components/site/image-lightbox';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    dateBadgeClassName,
    downloadButtonClassName,
    fileSizeBadgeClassName,
    languageBadgeClassName,
    platformBadgeClassName,
    releaseFooterClassName,
    releaseFooterInnerClassName,
    tagBadgeClassName,
} from '@/components/site/resource-detail-styles';
import { RichHtml } from '@/components/site/rich-html';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { show as downloadLinkShow } from '@/routes/download-links';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameDetail } from '@/types/resources';

type ResourceTab = 'details' | 'downloads' | 'screenshots';

/** Collapsed height ≈ three prose-sm lines before “Show more”. */
const RELEASE_DESCRIPTION_COLLAPSED_MAX_PX = 72;

const releaseDescriptionClassName = cn(
    'prose-sm max-w-none text-muted-foreground',
    '[--tw-prose-body:var(--color-muted-foreground)]',
    'prose-p:my-1.5 prose-p:leading-relaxed',
    'first:prose-p:mt-0 last:prose-p:mb-0',
    '[&:has(>p:only-child:empty)]:hidden',
);

type CollapsibleReleaseDescriptionProps = {
    html: string;
};

function CollapsibleReleaseDescription({
    html,
}: CollapsibleReleaseDescriptionProps) {
    const contentRef = useRef<HTMLDivElement>(null);
    const [expanded, setExpanded] = useState(false);
    const [overflows, setOverflows] = useState(false);

    useLayoutEffect(() => {
        const el = contentRef.current;

        if (!el) {
            return;
        }

        const measure = () => {
            setOverflows(
                el.scrollHeight > RELEASE_DESCRIPTION_COLLAPSED_MAX_PX + 1,
            );
        };

        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(el);

        return () => observer.disconnect();
    }, [html]);

    return (
        <div className="flex flex-col">
            <div className="relative">
                <div
                    ref={contentRef}
                    className={cn(!expanded && overflows && 'overflow-hidden')}
                    style={
                        !expanded && overflows
                            ? {
                                  maxHeight:
                                      RELEASE_DESCRIPTION_COLLAPSED_MAX_PX,
                              }
                            : undefined
                    }
                >
                    <RichHtml
                        html={html}
                        className={releaseDescriptionClassName}
                    />
                </div>
                {!expanded && overflows ? (
                    <div
                        className="pointer-events-none absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-card to-transparent"
                        aria-hidden
                    />
                ) : null}
            </div>
            {overflows ? (
                <button
                    type="button"
                    className={cn(
                        'mt-1.5 flex w-full items-center gap-2 text-[11px] tracking-wide text-muted-foreground uppercase',
                        'transition-colors hover:text-foreground',
                        'focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                    )}
                    aria-expanded={expanded}
                    onClick={() => setExpanded((value) => !value)}
                >
                    <span className="h-px min-w-0 flex-1 bg-border" />
                    <span className="inline-flex shrink-0 items-center gap-0.5">
                        {expanded ? 'Less' : 'More'}
                        {expanded ? (
                            <ChevronUp className="size-3 opacity-70" />
                        ) : (
                            <ChevronDown className="size-3 opacity-70" />
                        )}
                    </span>
                    <span className="h-px min-w-0 flex-1 bg-border" />
                </button>
            ) : null}
        </div>
    );
}

type ResourceScreenshotProps = {
    src: string;
    alt: string;
    onOpen: () => void;
};

export function ResourceScreenshot({
    src,
    alt,
    onOpen,
}: ResourceScreenshotProps) {
    const [loadedSrc, setLoadedSrc] = useState<string | null>(null);
    const loaded = loadedSrc === src;

    return (
        <button
            type="button"
            onClick={onOpen}
            className="relative aspect-video overflow-hidden rounded-md border border-border bg-card transition-[border-color] hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            aria-label={`View ${alt}`}
        >
            {!loaded ? (
                <div className="absolute inset-0 flex items-center justify-center bg-muted/40">
                    <Spinner className="size-5 text-muted-foreground" />
                </div>
            ) : null}
            <img
                src={src}
                alt={alt}
                className={cn(
                    'size-full object-cover transition-opacity duration-200',
                    loaded ? 'opacity-100' : 'opacity-0',
                )}
                loading="lazy"
                decoding="async"
                referrerPolicy="no-referrer"
                onLoad={() => setLoadedSrc(src)}
                onError={() => setLoadedSrc(src)}
            />
        </button>
    );
}

type Props = {
    resource: GameDetail;
    activeTab: ResourceTab;
    isTabPending: boolean;
    screenshotSlides: LightboxSlide[];
    onOpenLightbox: (slides: LightboxSlide[], index: number) => void;
    /** Site-wide notice HTML above download packages (empty when disabled). */
    resourceNotice?: string;
};

export function ResourceTabContent({
    resource,
    activeTab,
    isTabPending,
    screenshotSlides,
    onOpenLightbox,
    resourceNotice = '',
}: Props) {
    return (
        <div aria-busy={isTabPending || undefined}>
            {activeTab === 'details' ? (
                <section className="rounded-md border border-border bg-card p-4 sm:p-5">
                    {resource.tags.length > 0 ? (
                        <div className="mb-4 flex flex-wrap gap-2">
                            {resource.tags.map((tag) => (
                                <Link
                                    key={tag.slug}
                                    href={resourcesIndex.url({
                                        query: { tags: [tag.slug] },
                                    })}
                                    className={tagBadgeClassName}
                                    prefetch
                                >
                                    {tag.name}
                                </Link>
                            ))}
                        </div>
                    ) : null}
                    <RichHtml html={resource.description} />
                </section>
            ) : null}

            {activeTab === 'downloads' ? (
                <div className="flex flex-col gap-3 sm:gap-4">
                    {resourceNotice !== '' ? (
                        <div
                            role="note"
                            className={cn(
                                'flex gap-2.5 rounded-md border border-info/20 bg-info/8 px-3 py-2.5 sm:px-4',
                                'dark:border-info/25 dark:bg-info/12',
                            )}
                        >
                            <Info
                                className="mt-0.5 size-4 shrink-0 text-info"
                                aria-hidden
                            />
                            <RichHtml
                                html={resourceNotice}
                                className={cn(
                                    'min-w-0 flex-1 text-sm leading-relaxed',
                                    'prose-p:my-1 prose-p:first:mt-0 prose-p:last:mb-0',
                                    'prose-ul:my-1 prose-ol:my-1 prose-li:my-0',
                                    'prose-headings:my-1.5 prose-headings:text-sm',
                                    'prose-a:font-medium',
                                )}
                            />
                        </div>
                    ) : null}
                    {resource.releases.length > 0 ? (
                        resource.releases.map((release) => {
                            const hasLinks = release.downloadLinks.length > 0;
                            const multiLinks = release.downloadLinks.length > 1;

                            return (
                                <article
                                    key={release.id}
                                    className="overflow-hidden rounded-lg border border-border bg-card"
                                >
                                    {/* Header: title + version + description */}
                                    <div className="flex flex-col gap-2.5 px-4 py-4 sm:px-5">
                                        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                            <h3 className="min-w-0 font-heading text-base font-semibold tracking-tight text-foreground">
                                                {release.title ??
                                                    'Download package'}
                                            </h3>
                                            {release.version ? (
                                                <span className="inline-flex h-6 shrink-0 items-center rounded-md bg-muted px-2 text-xs font-medium text-muted-foreground">
                                                    v{release.version}
                                                </span>
                                            ) : null}
                                        </div>

                                        {release.description ? (
                                            <CollapsibleReleaseDescription
                                                html={release.description}
                                            />
                                        ) : null}
                                    </div>

                                    {/* Footer: meta chips (left) + download CTAs (right) */}
                                    <div className={releaseFooterClassName}>
                                        <div
                                            className={
                                                releaseFooterInnerClassName
                                            }
                                        >
                                            <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                {release.platforms.map(
                                                    (platform) => (
                                                        <Badge
                                                            key={platform.slug}
                                                            variant="outline"
                                                            className={platformBadgeClassName(
                                                                platform.slug,
                                                            )}
                                                        >
                                                            <PlatformIcon
                                                                slug={
                                                                    platform.slug
                                                                }
                                                                data-icon="inline-start"
                                                            />
                                                            {platform.name}
                                                        </Badge>
                                                    ),
                                                )}
                                                {release.languages.map(
                                                    (language) => (
                                                        <Badge
                                                            key={language}
                                                            variant="outline"
                                                            className={
                                                                languageBadgeClassName
                                                            }
                                                        >
                                                            {language}
                                                        </Badge>
                                                    ),
                                                )}
                                                {release.fileSize ? (
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            fileSizeBadgeClassName
                                                        }
                                                    >
                                                        <HardDrive data-icon="inline-start" />
                                                        {release.fileSize}
                                                    </Badge>
                                                ) : null}
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        dateBadgeClassName
                                                    }
                                                >
                                                    <CalendarDays data-icon="inline-start" />
                                                    <time
                                                        dateTime={
                                                            release.publishedAt ??
                                                            undefined
                                                        }
                                                    >
                                                        {release.publishedAt
                                                            ? formatDate(
                                                                  release.publishedAt,
                                                              )
                                                            : 'Unscheduled'}
                                                    </time>
                                                </Badge>
                                            </div>

                                            {hasLinks ? (
                                                <div
                                                    className={cn(
                                                        'flex w-full shrink-0 flex-wrap gap-2',
                                                        'sm:w-auto sm:justify-end',
                                                        multiLinks &&
                                                            'grid grid-cols-1 min-[380px]:grid-cols-2 sm:flex',
                                                    )}
                                                    role="group"
                                                    aria-label="Download links"
                                                >
                                                    {release.downloadLinks.map(
                                                        (link, index) => (
                                                            <Button
                                                                key={link.id}
                                                                asChild
                                                                variant="default"
                                                                size="sm"
                                                                className={cn(
                                                                    downloadButtonClassName,
                                                                    multiLinks &&
                                                                        'w-full sm:w-auto',
                                                                )}
                                                            >
                                                                <Link
                                                                    href={downloadLinkShow(
                                                                        link.id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    <Download data-icon="inline-start" />
                                                                    <span className="truncate">
                                                                        {link.label ||
                                                                            (multiLinks
                                                                                ? `Download ${index + 1}`
                                                                                : 'Download')}
                                                                    </span>
                                                                </Link>
                                                            </Button>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                        </div>
                                    </div>
                                </article>
                            );
                        })
                    ) : (
                        <SiteEmptyState
                            title="No downloads available yet"
                            description="Check back later for release packages and download links."
                        />
                    )}
                </div>
            ) : null}

            {activeTab === 'screenshots' ? (
                resource.screenshots.length > 0 ? (
                    <div className="grid grid-cols-1 content-start gap-3 sm:grid-cols-3">
                        {resource.screenshots.map((screenshot, index) => (
                            <ResourceScreenshot
                                key={screenshot}
                                src={screenshot}
                                alt={`${resource.title} screenshot ${index + 1}`}
                                onOpen={() =>
                                    onOpenLightbox(screenshotSlides, index)
                                }
                            />
                        ))}
                    </div>
                ) : (
                    <SiteEmptyState
                        icon={Images}
                        title="No images yet"
                        description="This resource does not have gallery images uploaded."
                    />
                )
            ) : null}
        </div>
    );
}
