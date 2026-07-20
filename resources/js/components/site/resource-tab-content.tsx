import { Link } from '@inertiajs/react';
import { CalendarDays, Download, HardDrive } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import type { LightboxSlide } from '@/components/site/image-lightbox';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    dateBadgeClassName,
    downloadButtonPalettes,
    fileSizeBadgeClassName,
    languageBadgeClassName,
    platformBadgeClassName,
} from '@/components/site/resource-detail-styles';
import { RichHtml } from '@/components/site/rich-html';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { show as downloadLinkShow } from '@/routes/download-links';
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
    const shouldReduceMotion = useReducedMotion();

    return (
        <div aria-busy={isTabPending || undefined}>
            <motion.div
                key={activeTab}
                initial={shouldReduceMotion ? false : { opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={
                    shouldReduceMotion
                        ? { duration: 0 }
                        : {
                              type: 'tween',
                              duration: 0.14,
                              ease: 'easeOut',
                          }
                }
            >
                {activeTab === 'details' ? (
                    <section className="rounded-md border border-border bg-card p-4 sm:p-5">
                        {resource.tags.length > 0 ? (
                            <div className="mb-4 flex flex-wrap gap-1.5">
                                {resource.tags.map((tag) => (
                                    <span
                                        key={tag}
                                        className="inline-flex h-6 items-center rounded-sm bg-muted px-2.5 text-xs font-medium text-muted-foreground"
                                    >
                                        {tag}
                                    </span>
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
                                    className="overflow-hidden rounded-md border border-border bg-card"
                                >
                                    {/* Header: title + version + description */}
                                    <div className="flex flex-col gap-2.5 border-b border-border/70 p-3 sm:p-4">
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                            <h3 className="font-heading text-sm font-semibold text-foreground sm:text-base">
                                                {release.title ??
                                                    'Download package'}
                                            </h3>
                                            {release.version ? (
                                                <span className="inline-flex h-6 items-center rounded-sm bg-muted px-2 text-xs font-medium text-muted-foreground">
                                                    v{release.version}
                                                </span>
                                            ) : null}
                                        </div>

                                        {release.description ? (
                                            <RichHtml
                                                html={release.description}
                                                className="prose-sm [--tw-prose-body:var(--color-muted-foreground)] prose-p:my-0 [&:has(>p:only-child:empty)]:hidden"
                                            />
                                        ) : null}
                                    </div>

                                    {/* Tags + actions */}
                                    <div className="flex flex-col gap-3 p-3 sm:p-4">
                                        <div className="flex flex-wrap gap-1.5">
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
                                                    {release.publishedAt ??
                                                        'Unscheduled'}
                                                </time>
                                            </Badge>
                                        </div>

                                        {release.downloadLinks.length > 0 ? (
                                            <div className="flex flex-wrap gap-2 pt-0.5">
                                                {release.downloadLinks.map(
                                                    (link, index) => (
                                                        <Button
                                                            key={link.id}
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                            className={cn(
                                                                'h-8 border shadow-none',
                                                                downloadButtonPalettes[
                                                                    index %
                                                                        downloadButtonPalettes.length
                                                                ],
                                                            )}
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
                            <p className="rounded-md border border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground">
                                No downloads available yet
                            </p>
                        )}
                    </div>
                ) : null}

                {activeTab === 'screenshots' ? (
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
                ) : null}
            </motion.div>
        </div>
    );
}
