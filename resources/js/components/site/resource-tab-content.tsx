import { Link } from '@inertiajs/react';
import { CalendarDays, Download, HardDrive, Images } from 'lucide-react';
import { useState } from 'react';
import type { LightboxSlide } from '@/components/site/image-lightbox';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    dateBadgeClassName,
    downloadButtonClassName,
    fileSizeBadgeClassName,
    languageBadgeClassName,
    platformBadgeClassName,
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
};

export function ResourceTabContent({
    resource,
    activeTab,
    isTabPending,
    screenshotSlides,
    onOpenLightbox,
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
                    <div className="flex flex-col gap-4">
                        {resource.releases.length > 0 ? (
                            resource.releases.map((release) => (
                                <article
                                    key={release.id}
                                    className="overflow-hidden rounded-lg border border-border bg-card"
                                >
                                    {/* Header: title + version + description */}
                                    <div className="flex flex-col gap-2.5 border-b border-border/70 px-4 py-4 sm:px-5">
                                        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                            <h3 className="font-heading text-base font-semibold tracking-tight text-foreground">
                                                {release.title ??
                                                    'Download package'}
                                            </h3>
                                            {release.version ? (
                                                <span className="inline-flex h-6 items-center rounded-md bg-muted px-2 text-xs font-medium text-muted-foreground">
                                                    v{release.version}
                                                </span>
                                            ) : null}
                                        </div>

                                        {release.description ? (
                                            <RichHtml
                                                html={release.description}
                                                className="prose-sm max-w-none text-muted-foreground [--tw-prose-body:var(--color-muted-foreground)] prose-p:my-1.5 prose-p:leading-relaxed first:prose-p:mt-0 last:prose-p:mb-0 [&:has(>p:only-child:empty)]:hidden"
                                            />
                                        ) : null}
                                    </div>

                                    {/* Tags + actions — stacked on mobile, one row on sm+ */}
                                    <div className="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-2.5 sm:px-5">
                                        <div className="flex min-w-0 flex-wrap gap-1.5 sm:flex-1">
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
                                                            slug={platform.slug}
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
                                                className={dateBadgeClassName}
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

                                        {release.downloadLinks.length > 0 ? (
                                            <div className="flex flex-wrap gap-2 sm:ml-auto sm:justify-end">
                                                {release.downloadLinks.map(
                                                    (link, index) => (
                                                        <Button
                                                            key={link.id}
                                                            asChild
                                                            variant="default"
                                                            size="sm"
                                                            className={downloadButtonClassName}
                                                        >
                                                            <Link
                                                                href={downloadLinkShow(
                                                                    link.id,
                                                                )}
                                                                prefetch
                                                            >
                                                                <Download data-icon="inline-start" />
                                                                {link.label ||
                                                                    (release
                                                                        .downloadLinks
                                                                        .length >
                                                                    1
                                                                        ? `Download ${index + 1}`
                                                                        : 'Download')}
                                                            </Link>
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        ) : null}
                                    </div>
                                </article>
                            ))
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
