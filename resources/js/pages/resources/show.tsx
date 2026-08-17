import { Link, router, useHttp, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarCheck,
    CalendarRange,
    Download,
    Eye,
    Pencil,
    RefreshCw,
    XIcon,
} from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { FavoriteButton } from '@/components/site/favorite-button';
import { ImageLightbox } from '@/components/site/image-lightbox';
import type { LightboxSlide } from '@/components/site/image-lightbox';
import type { PageSeoData } from '@/components/site/page-seo';
import { PageSeo } from '@/components/site/page-seo';
import { PlatformIcon } from '@/components/site/platform-icon';
import type { ResourceComment } from '@/components/site/resource-comments';
import {
    categoryBadgeClassName,
    downloadHeroButtonClassName,
    languageBadgeClassName,
    platformBadgeClassName,
} from '@/components/site/resource-detail-styles';
import { ResourceSourceMeta } from '@/components/site/resource-source-meta';
import { ResourceTabContent } from '@/components/site/resource-tab-content';
import { RouteTabs } from '@/components/site/route-tabs';
import { SitePageContainer } from '@/components/site/site-page-container';
import type { PaginatedData } from '@/components/site/site-pagination';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { useFavorite } from '@/hooks/use-favorite';
import { useImageLoadState } from '@/hooks/use-image-load-state';
import { SiteLayout } from '@/layouts/site-layout';
import {
    formatDate,
    formatReleaseDate,
    formatViews,
} from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import {
    details as resourceDetails,
    downloads as resourceDownloads,
    genre as resourcesGenre,
    index as resourcesIndex,
    language as resourcesLanguage,
    platform as resourcesPlatform,
    comments as resourceComments,
    screenshots as resourceScreenshots,
} from '@/routes/resources';
import { seen as markDownloadsSeen } from '@/routes/resources/downloads';
import { show as userShow } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import type { GameCard, GameDetail } from '@/types/resources';

type Props = {
    activeTab: ResourceTab;
    resource: GameDetail;
    /** Site-wide notice HTML from admin (empty string when disabled). */
    resourceNotice?: string;
    comments?: PaginatedData<ResourceComment>;
    commentsCount?: number;
    commentsEnabled?: boolean;
    ratingsAvg?: number;
    ratingsCount?: number;
    related?: GameCard[];
    pageSeo?: PageSeoData | null;
};

type ResourceTab = 'details' | 'downloads' | 'screenshots' | 'comments';

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
    {
        value: 'comments',
        label: 'Reviews',
        href: (resource) => resourceComments(resource).url,
    },
];

type ResourceHeroCoverProps = {
    src: string;
    fallbackSrc?: string;
    alt: string;
    clickable: boolean;
    onOpen?: () => void;
};

function ResourceHeroCover({
    src,
    fallbackSrc,
    alt,
    clickable,
    onOpen,
}: ResourceHeroCoverProps) {
    const {
        imageRef,
        loaded,
        markError,
        markLoaded,
        src: displaySrc,
    } = useImageLoadState(src, fallbackSrc);

    const image = (
        <img
            ref={imageRef}
            src={displaySrc}
            alt={alt}
            className={cn(
                'size-full object-cover transition-opacity duration-200',
                loaded ? 'opacity-100' : 'opacity-0',
            )}
            loading="eager"
            decoding="async"
            fetchPriority="high"
            referrerPolicy="no-referrer"
            onLoad={markLoaded}
            onError={markError}
        />
    );

    const placeholder = !loaded ? (
        <div
            className="absolute inset-0 flex items-center justify-center bg-muted"
            aria-hidden
        >
            <div className="absolute inset-0 animate-pulse bg-muted-foreground/10" />
            <Spinner className="relative size-6 text-muted-foreground" />
        </div>
    ) : null;

    if (clickable) {
        return (
            <button
                type="button"
                onClick={onOpen}
                className={cn(
                    'relative size-full cursor-zoom-in focus-visible:outline-none',
                    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset',
                )}
                aria-label={`View full size cover for ${alt}`}
            >
                {placeholder}
                {image}
            </button>
        );
    }

    return (
        <div className="relative size-full">
            {placeholder}
            {image}
        </div>
    );
}

export default function ResourceShow({
    activeTab,
    resource,
    resourceNotice = '',
    comments,
    commentsCount = 0,
    commentsEnabled = true,
    ratingsAvg = 0,
    ratingsCount = 0,
    related = [],
    pageSeo,
}: Props) {
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

    const resourceTabLinks = resourceTabs
        .filter((tab) => tab.value !== 'comments' || commentsEnabled)
        .map((tab) => ({
            value: tab.value,
            label:
                tab.value === 'comments' && commentsCount > 0
                    ? `${tab.label} (${commentsCount})`
                    : tab.label,
            href: tab.href(resource.id),
        }));

    const screenshotSlides = resource.screenshots.map((src, index) => ({
        src,
        alt: `${resource.title} screenshot ${index + 1}`,
    }));

    const hasCover = Boolean(resource.cover || resource.thumbnail);
    const coverSrc = resource.cover || resource.thumbnail;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Home', href: home() },
        { title: 'Resources', href: resourcesIndex() },
        { title: resource.title, href: resourceDetails(resource.id) },
    ];

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
            <PageSeo seo={pageSeo} title={resource.title} />

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
                            className="block cursor-zoom-in focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
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
                <Breadcrumbs breadcrumbs={breadcrumbs} />

                <section className="overflow-hidden rounded-md border border-border bg-card">
                    <div className="flex flex-col md:flex-row">
                        <div className="aspect-video w-full shrink-0 overflow-hidden bg-muted md:aspect-auto md:h-[280px] md:w-auto md:max-w-[498px]">
                            <ResourceHeroCover
                                src={resource.thumbnail}
                                fallbackSrc={coverSrc}
                                alt={resource.title}
                                clickable={hasCover}
                                onOpen={() => setCoverDialogOpen(true)}
                            />
                        </div>

                        <div className="flex min-w-0 flex-1 flex-col gap-2.5 px-4 py-3 md:gap-3 md:px-5 md:py-4">
                            {/* 1. Title + subtitle */}
                            <div className="min-w-0 space-y-1">
                                <h1 className="font-heading text-xl font-semibold tracking-tight text-foreground md:text-2xl">
                                    {resource.title}
                                </h1>

                                {resource.subtitle ? (
                                    <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                                        {resource.subtitle}
                                    </p>
                                ) : null}
                            </div>

                            {/* 2. Classification chips */}
                            <div className="flex flex-wrap gap-1.5">
                                {resource.categorySlug ? (
                                    <Badge
                                        variant="ghost"
                                        className={categoryBadgeClassName}
                                        asChild
                                    >
                                        <Link
                                            href={resourcesGenre.url(
                                                resource.categorySlug,
                                            )}
                                            prefetch
                                        >
                                            {resource.category}
                                        </Link>
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="ghost"
                                        className={categoryBadgeClassName}
                                    >
                                        {resource.category}
                                    </Badge>
                                )}
                                {resource.platforms.map((platform) => (
                                    <Badge
                                        key={platform.slug}
                                        variant="ghost"
                                        className={platformBadgeClassName(
                                            platform.slug,
                                        )}
                                        asChild
                                    >
                                        <Link
                                            href={resourcesPlatform.url(
                                                platform.slug,
                                            )}
                                            prefetch
                                        >
                                            <PlatformIcon
                                                slug={platform.slug}
                                                data-icon="inline-start"
                                            />
                                            {platform.name}
                                        </Link>
                                    </Badge>
                                ))}
                                {resource.languages.map((language) => (
                                    <Badge
                                        key={language.code}
                                        variant="ghost"
                                        className={languageBadgeClassName}
                                        asChild
                                    >
                                        <Link
                                            href={resourcesLanguage.url(
                                                language.code,
                                            )}
                                            prefetch
                                        >
                                            {language.name}
                                        </Link>
                                    </Badge>
                                ))}
                            </div>

                            {/* 3. Catalog meta (game product) */}
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-muted-foreground">
                                <span className="inline-flex max-w-full min-w-0 items-center gap-1.5">
                                    <Building2
                                        className="size-3.5 shrink-0 opacity-70"
                                        aria-hidden
                                    />
                                    <span className="truncate">
                                        {resource.developer}
                                    </span>
                                </span>

                                {resource.source ? (
                                    <ResourceSourceMeta
                                        source={resource.source}
                                    />
                                ) : null}

                                {resource.releaseDate ? (
                                    <span className="inline-flex items-center gap-1.5">
                                        <CalendarRange
                                            className="size-3.5 shrink-0 opacity-70"
                                            aria-hidden
                                        />
                                        <span className="text-muted-foreground/80">
                                            Released
                                        </span>
                                        <span>
                                            {formatReleaseDate(
                                                resource.releaseDate,
                                            )}
                                        </span>
                                    </span>
                                ) : null}
                            </div>

                            {/* 4. Site meta — latest contributor; keep first listed + optional update */}
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-muted-foreground">
                                {resource.contributors[0] ? (
                                    <Link
                                        href={userShow(
                                            resource.contributors[0].slug,
                                        )}
                                        prefetch
                                        className={cn(
                                            'inline-flex min-w-0 max-w-40 items-center gap-1.5 rounded-sm',
                                            'text-foreground/90 transition-colors hover:text-foreground',
                                            'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                        )}
                                        title={`Contributed by ${resource.contributors[0].name}`}
                                    >
                                        <UserAvatar
                                            user={resource.contributors[0]}
                                            className="size-5"
                                            fallbackClassName="bg-muted text-[10px] text-muted-foreground"
                                        />
                                        <span className="truncate font-medium">
                                            {resource.contributors[0].name}
                                        </span>
                                    </Link>
                                ) : null}
                                <span
                                    className="inline-flex items-center gap-1.5"
                                    title="Package listed"
                                >
                                    <CalendarCheck
                                        className="size-3.5 shrink-0 opacity-70"
                                        aria-hidden
                                    />
                                    <span className="text-muted-foreground/80">
                                        Listed
                                    </span>
                                    <time
                                        dateTime={
                                            resource.publishedAt ?? undefined
                                        }
                                    >
                                        {resource.publishedAt
                                            ? formatDate(resource.publishedAt)
                                            : '—'}
                                    </time>
                                </span>
                                {resource.downloadsUpdatedAt ? (
                                    <span
                                        className="inline-flex items-center gap-1.5"
                                        title="Downloads last updated"
                                    >
                                        <RefreshCw
                                            className="size-3.5 shrink-0 opacity-70"
                                            aria-hidden
                                        />
                                        <span className="text-muted-foreground/80">
                                            Updated
                                        </span>
                                        <time
                                            dateTime={
                                                resource.downloadsUpdatedAt
                                            }
                                        >
                                            {formatDate(
                                                resource.downloadsUpdatedAt,
                                            )}
                                        </time>
                                    </span>
                                ) : null}
                                <span
                                    className="inline-flex items-center gap-1.5"
                                    title="Views"
                                >
                                    <Eye
                                        className="size-3.5 shrink-0 opacity-70"
                                        aria-hidden
                                    />
                                    {formatViews(resource.views)}
                                </span>
                            </div>

                            {/* 5. Actions */}
                            <div className="mt-auto w-full max-w-full pt-1">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {resource.hasDownloads ? (
                                        <Button
                                            size="default"
                                            variant="secondary"
                                            className={
                                                downloadHeroButtonClassName
                                            }
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    resourceDownloads(
                                                        resource.id,
                                                    ).url
                                                }
                                                headers={{
                                                    'X-Resource-Tab-Nav': '1',
                                                }}
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
                                            size="default"
                                            variant="secondary"
                                            className="h-9 border-0 px-3.5 shadow-none"
                                            disabled
                                        >
                                            <Download data-icon="inline-start" />
                                            Unavailable
                                        </Button>
                                    )}
                                    <FavoriteButton
                                        showLabel
                                        isFavorited={isFavorite}
                                        isToggling={isTogglingFavorite}
                                        onToggle={handleFavoriteClick}
                                    />
                                    {resource.adminEditUrl ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-9"
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
                                    <p className="w-full self-end text-left text-xs leading-5 whitespace-nowrap text-muted-foreground md:ml-auto md:w-auto md:text-right">
                                        Favorite for update alerts.
                                    </p>
                                </div>
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
                        resourceNotice={resourceNotice}
                        comments={comments}
                        commentsCount={commentsCount}
                        ratingsAvg={ratingsAvg}
                        ratingsCount={ratingsCount}
                        resourceId={resource.id}
                        related={related}
                    />
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
