import { Head, Link, router, useHttp, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Download,
    Eye,
    Pencil,
    XIcon,
} from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import { FavoriteButton } from '@/components/site/favorite-button';
import { ImageLightbox } from '@/components/site/image-lightbox';
import type { LightboxSlide } from '@/components/site/image-lightbox';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    categoryBadgeClassName,
    languageBadgeClassName,
    platformBadgeClassName,
} from '@/components/site/resource-detail-styles';
import { ResourceTabContent } from '@/components/site/resource-tab-content';
import { RouteTabs } from '@/components/site/route-tabs';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useFavorite } from '@/hooks/use-favorite';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import {
    details as resourceDetails,
    downloads as resourceDownloads,
    screenshots as resourceScreenshots,
} from '@/routes/resources';
import { seen as markDownloadsSeen } from '@/routes/resources/downloads';
import type { GameDetail } from '@/types/resources';

type Props = {
    activeTab: ResourceTab;
    resource: GameDetail;
};

type ResourceTab = 'details' | 'downloads' | 'screenshots';

const resourceTabs: Array<{
    value: ResourceTab;
    label: string;
    href: (resource: string) => string;
}> = [
    {
        value: 'details',
        label: 'Details',
        href: (resource) => resourceDetails(resource).url,
    },
    {
        value: 'downloads',
        label: 'Downloads',
        href: (resource) => resourceDownloads(resource).url,
    },
    {
        value: 'screenshots',
        label: 'Screenshots',
        href: (resource) => resourceScreenshots(resource).url,
    },
];

export default function ResourceShow({ activeTab, resource }: Props) {
    const shouldReduceMotion = useReducedMotion();
    const [pendingTab, setPendingTab] = useState<ResourceTab | null>(null);
    const {
        isFavorited: isFavorite,
        isToggling: isTogglingFavorite,
        toggleFavorite: handleFavoriteClick,
    } = useFavorite({
        resourceId: resource.id,
        initialIsFavorited: resource.isFavorited,
    });
    const [lightboxSlides, setLightboxSlides] = useState<LightboxSlide[]>([]);
    const [lightboxIndex, setLightboxIndex] = useState(-1);
    const [coverDialogOpen, setCoverDialogOpen] = useState(false);
    const page = usePage();
    const { auth } = page.props;
    const authUserId = auth.user?.id;
    const { post: postDownloadsSeen } = useHttp<Record<string, never>>();
    const tabsListRef = useRef<HTMLElement | null>(null);
    const tabRefs = useRef<Partial<Record<ResourceTab, HTMLElement | null>>>(
        {},
    );
    const shouldScrollToDownloads = useRef(false);
    const navigationSequence = useRef(0);
    const pendingNavigation = useRef<{
        id: number;
        tab: ResourceTab;
    } | null>(null);
    const isTabPending = pendingTab !== null;
    const displayedTab = pendingTab ?? activeTab;

    useEffect(() => {
        if (activeTab !== 'downloads' || authUserId === undefined) {
            return;
        }

        void postDownloadsSeen(markDownloadsSeen(resource.id).url);
    }, [activeTab, authUserId, postDownloadsSeen, resource.id]);

    useEffect(() => {
        const pending = pendingNavigation.current;

        if (pending?.tab !== activeTab) {
            if (pending) {
                pendingNavigation.current = null;
                setPendingTab(null);
                shouldScrollToDownloads.current = false;
            }

            return;
        }

        pendingNavigation.current = null;
        setPendingTab(null);
    }, [activeTab]);

    useEffect(() => {
        const resetPendingNavigation = () => {
            const pending = pendingNavigation.current;

            if (!pending) {
                return;
            }

            pendingNavigation.current = null;
            setPendingTab(null);
            shouldScrollToDownloads.current = false;
        };

        const removeHttpExceptionListener = router.on(
            'httpException',
            resetPendingNavigation,
        );
        const removeNetworkErrorListener = router.on(
            'networkError',
            resetPendingNavigation,
        );

        return () => {
            removeHttpExceptionListener();
            removeNetworkErrorListener();
        };
    }, []);

    useEffect(() => {
        if (activeTab !== 'downloads' || !shouldScrollToDownloads.current) {
            return;
        }

        shouldScrollToDownloads.current = false;

        const frame = window.requestAnimationFrame(() => {
            tabsListRef.current?.scrollIntoView({
                behavior: shouldReduceMotion ? 'auto' : 'smooth',
                block: 'start',
            });
            tabRefs.current.downloads?.focus({ preventScroll: true });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [activeTab, shouldReduceMotion]);

    const beginTabNavigation = (
        tab: ResourceTab,
        navigationId?: number,
    ): number => {
        const id = navigationId ?? navigationSequence.current + 1;
        navigationSequence.current = Math.max(navigationSequence.current, id);
        pendingNavigation.current = { id, tab };
        setPendingTab(tab);

        return id;
    };

    const settleTabNavigation = (id: number | null, tab: ResourceTab) => {
        const pending = pendingNavigation.current;

        if (
            !pending ||
            (id !== null && pending.id !== id) ||
            (id === null && pending.tab !== tab)
        ) {
            return;
        }

        if (activeTab !== tab) {
            return;
        }

        pendingNavigation.current = null;
        setPendingTab(null);
    };

    const rollbackTabNavigation = (id: number | null, tab?: ResourceTab) => {
        const pending = pendingNavigation.current;

        if (
            !pending ||
            (id !== null && pending.id !== id) ||
            (id === null && tab !== undefined && pending.tab !== tab)
        ) {
            return;
        }

        pendingNavigation.current = null;
        setPendingTab(null);
        shouldScrollToDownloads.current = false;
    };

    const handleTabStart = (
        tab: ResourceTab,
        scrollToDownloads = false,
        navigationId?: number,
    ): number | null => {
        if (scrollToDownloads) {
            shouldScrollToDownloads.current = true;
        }

        if (tab === activeTab && pendingNavigation.current === null) {
            return null;
        }

        return beginTabNavigation(tab, navigationId);
    };

    const resourceTabLinks = resourceTabs.map((tab) => ({
        value: tab.value,
        label: tab.label,
        href: tab.href(resource.id),
    }));

    const screenshotSlides = resource.screenshots.map((src, index) => ({
        src,
        alt: `${resource.title} screenshot ${index + 1}`,
    }));

    const hasCover = Boolean(resource.cover || resource.thumbnail);
    const coverSrc = resource.cover || resource.thumbnail;

    const openLightbox = (slides: LightboxSlide[], index: number) => {
        if (slides.length === 0) {
            return;
        }

        setLightboxSlides(slides);
        setLightboxIndex(index);
    };

    const closeLightbox = () => {
        setLightboxIndex(-1);
    };

    return (
        <SiteLayout>
            <Head title={`${resource.title} - hgame`} />

            <ImageLightbox
                slides={lightboxSlides}
                index={lightboxIndex}
                onClose={closeLightbox}
                onIndexChange={setLightboxIndex}
            />

            <Dialog open={coverDialogOpen} onOpenChange={setCoverDialogOpen}>
                <DialogContent
                    showCloseButton={false}
                    className={cn(
                        'max-h-[min(90vh,900px)] w-full max-w-[min(96vw,56rem)] gap-3 overflow-hidden p-3 sm:p-4',
                        'bg-popover sm:max-w-[min(96vw,56rem)]',
                    )}
                >
                    <DialogHeader className="flex-row items-center justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="line-clamp-1 pr-1">
                                {resource.title}
                            </DialogTitle>
                            <DialogDescription className="sr-only">
                                Full size cover image for {resource.title}
                            </DialogDescription>
                        </div>
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                className="shrink-0"
                                aria-label="Close"
                            >
                                <XIcon />
                            </Button>
                        </DialogClose>
                    </DialogHeader>
                    <div className="overflow-hidden rounded-md bg-muted">
                        <a
                            href={coverSrc}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block cursor-zoom-in focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            aria-label={`Open full size cover for ${resource.title}`}
                        >
                            <img
                                src={coverSrc}
                                alt={resource.title}
                                className="mx-auto max-h-[min(78vh,800px)] w-auto max-w-full object-contain"
                                referrerPolicy="no-referrer"
                            />
                        </a>
                    </div>
                </DialogContent>
            </Dialog>

            <SitePageContainer density="compact">
                <section className="overflow-hidden rounded-md border border-border bg-card">
                    <div className="flex flex-col sm:flex-row">
                        <div className="aspect-video w-full shrink-0 overflow-hidden bg-muted sm:aspect-auto sm:h-[280px] sm:w-auto sm:max-w-[498px]">
                            {hasCover ? (
                                <button
                                    type="button"
                                    onClick={() => setCoverDialogOpen(true)}
                                    className={cn(
                                        'size-full cursor-zoom-in focus-visible:outline-none',
                                        'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset',
                                    )}
                                    aria-label={`View full size cover for ${resource.title}`}
                                >
                                    <img
                                        src={resource.thumbnail}
                                        alt={resource.title}
                                        className="size-full object-cover"
                                        referrerPolicy="no-referrer"
                                    />
                                </button>
                            ) : (
                                <img
                                    src={resource.thumbnail}
                                    alt={resource.title}
                                    className="size-full object-cover"
                                    referrerPolicy="no-referrer"
                                />
                            )}
                        </div>

                        <div className="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:p-5">
                            <div className="flex flex-wrap gap-1.5">
                                <Badge
                                    variant="outline"
                                    className={categoryBadgeClassName}
                                >
                                    {resource.category}
                                </Badge>
                                {resource.platforms.map((platform) => (
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
                                ))}
                                {resource.languages.map((language) => (
                                    <Badge
                                        key={language}
                                        variant="outline"
                                        className={languageBadgeClassName}
                                    >
                                        {language}
                                    </Badge>
                                ))}
                            </div>

                            <div className="space-y-1">
                                <h1 className="font-heading text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                                    {resource.title}
                                </h1>

                                {resource.subtitle ? (
                                    <p className="text-sm leading-relaxed text-muted-foreground sm:text-base">
                                        {resource.subtitle}
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                                <span className="inline-flex min-w-0 items-center gap-1.5">
                                    <Building2
                                        className="size-3.5 shrink-0"
                                        aria-hidden
                                    />
                                    <span className="truncate">
                                        {resource.developer}
                                    </span>
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <CalendarDays
                                        className="size-3.5 shrink-0"
                                        aria-hidden
                                    />
                                    <time
                                        dateTime={
                                            resource.releaseDate ?? undefined
                                        }
                                    >
                                        {resource.releaseDate
                                            ? formatDate(resource.releaseDate)
                                            : '—'}
                                    </time>
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Eye
                                        className="size-3.5 shrink-0"
                                        aria-hidden
                                    />
                                    {new Intl.NumberFormat('en-US').format(
                                        resource.views,
                                    )}
                                </span>
                            </div>

                            <div className="mt-auto flex flex-col gap-2 pt-1">
                                <div className="flex items-center gap-2">
                                    {resource.hasDownloads ? (
                                        <Button
                                            size="lg"
                                            className={cn(
                                                'border-0 px-4 shadow-none',
                                                'bg-primary text-primary-foreground hover:bg-primary/90',
                                            )}
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    resourceDownloads(
                                                        resource.id,
                                                    ).url
                                                }
                                                preserveState
                                                preserveScroll
                                                onClick={(event) => {
                                                    if (
                                                        activeTab ===
                                                            'downloads' &&
                                                        pendingNavigation.current ===
                                                            null
                                                    ) {
                                                        event.preventDefault();
                                                        tabsListRef.current?.scrollIntoView(
                                                            {
                                                                behavior:
                                                                    shouldReduceMotion
                                                                        ? 'auto'
                                                                        : 'smooth',
                                                                block: 'start',
                                                            },
                                                        );
                                                        tabRefs.current.downloads?.focus(
                                                            {
                                                                preventScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                                onStart={() =>
                                                    handleTabStart(
                                                        'downloads',
                                                        true,
                                                    )
                                                }
                                                onSuccess={() =>
                                                    settleTabNavigation(
                                                        null,
                                                        'downloads',
                                                    )
                                                }
                                                onError={() => {
                                                    rollbackTabNavigation(
                                                        null,
                                                        'downloads',
                                                    );
                                                }}
                                                onCancel={() =>
                                                    rollbackTabNavigation(
                                                        null,
                                                        'downloads',
                                                    )
                                                }
                                            >
                                                <Download data-icon="inline-start" />
                                                Download
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            size="lg"
                                            variant="secondary"
                                            className="border-0 px-4 shadow-none"
                                            disabled
                                        >
                                            <Download data-icon="inline-start" />
                                            Unavailable
                                        </Button>
                                    )}
                                    <FavoriteButton
                                        isFavorited={isFavorite}
                                        isToggling={isTogglingFavorite}
                                        onToggle={handleFavoriteClick}
                                    />
                                    {resource.adminEditUrl ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="icon-lg"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            resource.adminEditUrl
                                                        }
                                                        aria-label="Edit in admin"
                                                    >
                                                        <Pencil />
                                                    </a>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Edit in admin
                                            </TooltipContent>
                                        </Tooltip>
                                    ) : null}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {isFavorite
                                        ? 'Favorited — download updates will show on your Favorites page.'
                                        : 'Favorite this resource to get download update alerts.'}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="flex flex-col gap-4">
                    <RouteTabs
                        tabs={resourceTabLinks}
                        activeValue={activeTab}
                        displayedValue={displayedTab}
                        ariaLabel={`${resource.title} sections`}
                        listRef={tabsListRef}
                        tabRefs={tabRefs}
                        onStart={(tab, navigationId) =>
                            handleTabStart(tab, false, navigationId)
                        }
                        onSuccess={(tab, navigationId) =>
                            settleTabNavigation(navigationId, tab)
                        }
                        onError={(tab, navigationId) =>
                            rollbackTabNavigation(navigationId, tab)
                        }
                        onCancel={(tab, navigationId) =>
                            rollbackTabNavigation(navigationId, tab)
                        }
                    />

                    <ResourceTabContent
                        resource={resource}
                        activeTab={activeTab}
                        isTabPending={isTabPending}
                        screenshotSlides={screenshotSlides}
                        onOpenLightbox={openLightbox}
                    />
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
