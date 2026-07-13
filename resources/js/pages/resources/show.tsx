import { Head, Link, router } from '@inertiajs/react';
import { Building2, CalendarDays, Download, HardDrive, Heart } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { PlatformIcon } from '@/components/site/platform-icon';
import { RichHtml } from '@/components/site/rich-html';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import {
    details as resourceDetails,
    downloads as resourceDownloads,
    screenshots as resourceScreenshots,
} from '@/routes/resources';
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

function isResourceTab(value: unknown): value is ResourceTab {
    return resourceTabs.some((tab) => tab.value === value);
}

const tabTriggerClassName = cn(
    'relative z-10 h-9 rounded-lg border-transparent px-4 text-sm font-medium shadow-none',
    'text-muted-foreground transition-colors',
    'hover:bg-transparent hover:text-foreground/80',
    'data-active:border-transparent data-active:bg-transparent data-active:text-foreground data-active:shadow-none',
    'data-active:hover:bg-transparent data-active:hover:text-foreground',
    'group-data-[variant=default]/tabs-list:data-active:shadow-none',
    'dark:hover:text-foreground/80 dark:data-active:border-transparent dark:data-active:bg-transparent',
    'dark:data-active:hover:bg-transparent dark:data-active:hover:text-foreground',
);

const heroBadgeClassName = cn(
    'h-6 gap-1 rounded-full border px-2.5 text-xs font-medium shadow-none',
    '[&>svg]:size-3.5!',
);

const categoryBadgeClassName = cn(
    heroBadgeClassName,
    'border-violet-500/25 bg-violet-500/12 text-violet-700 dark:text-violet-300',
);

const languageBadgeClassName = cn(
    heroBadgeClassName,
    'border-amber-500/25 bg-amber-500/12 text-amber-800 dark:text-amber-300',
);

const platformBadgeClassNames: Record<string, string> = {
    windows: cn(
        heroBadgeClassName,
        'border-sky-500/25 bg-sky-500/12 text-sky-700 dark:text-sky-300',
    ),
    ios: cn(
        heroBadgeClassName,
        'border-rose-500/25 bg-rose-500/12 text-rose-700 dark:text-rose-300',
    ),
    android: cn(
        heroBadgeClassName,
        'border-emerald-500/25 bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
    ),
};

function platformBadgeClassName(slug: string): string {
    return (
        platformBadgeClassNames[slug.toLowerCase()] ??
        cn(
            heroBadgeClassName,
            'border-indigo-500/25 bg-indigo-500/12 text-indigo-700 dark:text-indigo-300',
        )
    );
}

const metaChipClassName = cn(
    'inline-flex h-6 items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium',
);

const fileSizeChipClassName = cn(
    metaChipClassName,
    'border-teal-500/25 bg-teal-500/12 text-teal-800 dark:text-teal-300',
);

const dateChipClassName = cn(
    metaChipClassName,
    'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
);

const downloadButtonPalettes = [
    'border-sky-500/25 bg-sky-500/10 text-sky-800 hover:bg-sky-500/15 dark:text-sky-200',
    'border-violet-500/25 bg-violet-500/10 text-violet-800 hover:bg-violet-500/15 dark:text-violet-200',
    'border-emerald-500/25 bg-emerald-500/10 text-emerald-800 hover:bg-emerald-500/15 dark:text-emerald-200',
    'border-amber-500/25 bg-amber-500/10 text-amber-900 hover:bg-amber-500/15 dark:text-amber-200',
    'border-rose-500/25 bg-rose-500/10 text-rose-800 hover:bg-rose-500/15 dark:text-rose-200',
] as const;

export default function ResourceShow({ activeTab, resource }: Props) {
    const shouldReduceMotion = useReducedMotion();
    const [visualActiveTab, setVisualActiveTab] =
        useState<ResourceTab>(activeTab);
    const [isFavorite, setIsFavorite] = useState(false);
    const [loadedScreenshots, setLoadedScreenshots] = useState<Set<string>>(
        () => new Set(),
    );
    const tabsListRef = useRef<HTMLDivElement>(null);
    const tabRefs = useRef<Partial<Record<ResourceTab, HTMLElement | null>>>(
        {},
    );
    const shouldScrollToDownloads = useRef(false);
    const [pill, setPill] = useState({ left: 0, width: 0, ready: false });

    useEffect(() => {
        const resetVisualActiveTab = () => setVisualActiveTab(activeTab);
        const removeNavigateListener = router.on('navigate', (event) => {
            const page = event.detail.page;
            const nextActiveTab = page.props.activeTab;

            if (
                page.component === 'resources/show' &&
                isResourceTab(nextActiveTab)
            ) {
                setVisualActiveTab(nextActiveTab);
            }
        });
        const removeHttpExceptionListener = router.on(
            'httpException',
            resetVisualActiveTab,
        );
        const removeNetworkErrorListener = router.on(
            'networkError',
            resetVisualActiveTab,
        );

        return () => {
            removeNavigateListener();
            removeHttpExceptionListener();
            removeNetworkErrorListener();
        };
    }, [activeTab]);

    useLayoutEffect(() => {
        const updatePill = () => {
            const list = tabsListRef.current;
            const activeTrigger = tabRefs.current[visualActiveTab];

            if (!list || !activeTrigger) {
                return;
            }

            const listRect = list.getBoundingClientRect();
            const triggerRect = activeTrigger.getBoundingClientRect();

            setPill({
                left: triggerRect.left - listRect.left,
                width: triggerRect.width,
                ready: true,
            });
        };

        updatePill();

        if (
            shouldScrollToDownloads.current &&
            visualActiveTab === 'downloads'
        ) {
            shouldScrollToDownloads.current = false;
            tabsListRef.current?.scrollIntoView({
                behavior: 'auto',
                block: 'start',
            });
            tabRefs.current.downloads?.focus({ preventScroll: true });
        }

        window.addEventListener('resize', updatePill);

        return () => window.removeEventListener('resize', updatePill);
    }, [visualActiveTab]);

    const showDownloads = () => {
        if (visualActiveTab === 'downloads') {
            tabsListRef.current?.scrollIntoView({
                behavior: 'auto',
                block: 'start',
            });
            tabRefs.current.downloads?.focus({ preventScroll: true });

            return;
        }

        shouldScrollToDownloads.current = true;
        setVisualActiveTab('downloads');
    };

    const markScreenshotLoaded = (screenshot: string) => {
        setLoadedScreenshots((loaded) => new Set(loaded).add(screenshot));
    };

    return (
        <SiteLayout>
            <Head title={`${resource.title} - hgame`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-md bg-card ring-1 ring-foreground/10">
                    <div className="flex flex-col sm:flex-row">
                        <div className="aspect-video w-full shrink-0 overflow-hidden bg-muted sm:aspect-auto sm:h-[280px] sm:w-auto sm:max-w-[498px]">
                            <img
                                src={resource.thumbnail}
                                alt={resource.title}
                                className="size-full object-cover"
                                referrerPolicy="no-referrer"
                            />
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
                                        {resource.releaseDate ?? '—'}
                                    </time>
                                </span>
                            </div>

                            <div className="mt-auto flex items-center gap-2 pt-1">
                                <Button size="lg" asChild>
                                    <Link
                                        href={
                                            resourceDownloads(resource.id).url
                                        }
                                        preserveState
                                        preserveScroll
                                        onStart={showDownloads}
                                        onError={() => {
                                            shouldScrollToDownloads.current = false;
                                            setVisualActiveTab(activeTab);
                                        }}
                                    >
                                        <Download data-icon="inline-start" />
                                        Download
                                    </Link>
                                </Button>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant={
                                                isFavorite
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            size="icon-lg"
                                            aria-label={
                                                isFavorite
                                                    ? 'Remove from favorites'
                                                    : 'Add to favorites'
                                            }
                                            aria-pressed={isFavorite}
                                            onClick={() =>
                                                setIsFavorite(!isFavorite)
                                            }
                                        >
                                            <Heart
                                                className={
                                                    isFavorite
                                                        ? 'fill-current'
                                                        : undefined
                                                }
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {isFavorite
                                            ? 'Remove from favorites'
                                            : 'Add to favorites'}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                    </div>
                </section>

                <Tabs value={visualActiveTab} className="gap-4">
                    <TabsList
                        ref={tabsListRef}
                        className="relative grid h-auto w-full scroll-mt-20 grid-cols-3 gap-0.5 rounded-xl bg-card p-1 ring-1 ring-foreground/10 group-data-horizontal/tabs:h-auto sm:inline-grid sm:w-auto"
                    >
                        {pill.ready ? (
                            <motion.span
                                aria-hidden
                                className="absolute top-1 bottom-1 rounded-lg bg-muted"
                                initial={false}
                                animate={{
                                    left: pill.left,
                                    width: pill.width,
                                }}
                                transition={
                                    shouldReduceMotion
                                        ? { duration: 0 }
                                        : {
                                              type: 'tween',
                                              duration: 0.2,
                                              ease: 'easeInOut',
                                          }
                                }
                            />
                        ) : null}
                        {resourceTabs.map((tab) => (
                            <TabsTrigger
                                key={tab.value}
                                value={tab.value}
                                asChild
                                ref={(node) => {
                                    tabRefs.current[tab.value] = node;
                                }}
                                className={tabTriggerClassName}
                            >
                                <Link
                                    href={tab.href(resource.id)}
                                    preserveState
                                    preserveScroll
                                    onStart={() =>
                                        setVisualActiveTab(tab.value)
                                    }
                                    onError={() =>
                                        setVisualActiveTab(activeTab)
                                    }
                                >
                                    {tab.label}
                                </Link>
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="details">
                        <section className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                            <h2 className="mb-3 font-heading text-base font-semibold text-foreground">
                                About
                            </h2>
                            <RichHtml html={resource.description} />

                            {resource.tags.length > 0 ? (
                                <div className="mt-6">
                                    <h2 className="mb-3 font-heading text-base font-semibold text-foreground">
                                        Tags
                                    </h2>
                                    <div className="flex flex-wrap gap-1.5">
                                        {resource.tags.map((tag) => (
                                            <span
                                                key={tag}
                                                className="inline-flex h-6 items-center rounded-full bg-muted px-2.5 text-xs font-medium text-muted-foreground"
                                            >
                                                {tag}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ) : null}
                        </section>
                    </TabsContent>

                    <TabsContent value="downloads">
                        <div className="flex flex-col gap-4">
                            {resource.releases.map((release) => {
                                return (
                                    <article
                                        key={release.id}
                                        className="overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10"
                                    >
                                        <div className="flex flex-col gap-1 border-b border-foreground/5 bg-gradient-to-r from-sky-500/8 via-violet-500/6 to-transparent px-4 py-3.5 sm:px-5">
                                            <h3 className="font-heading text-base font-semibold tracking-tight text-foreground">
                                                {release.title ??
                                                    'Download package'}
                                            </h3>
                                            {release.version ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Version {release.version}
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="space-y-4 p-4 sm:p-5">
                                            {release.description ? (
                                                <RichHtml
                                                    html={release.description}
                                                />
                                            ) : null}

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
                                                    <span
                                                        className={
                                                            fileSizeChipClassName
                                                        }
                                                    >
                                                        <HardDrive className="size-3.5" />
                                                        {release.fileSize}
                                                    </span>
                                                ) : null}
                                                <span
                                                    className={dateChipClassName}
                                                >
                                                    <CalendarDays className="size-3.5" />
                                                    <time
                                                        dateTime={
                                                            release.publishedAt ??
                                                            undefined
                                                        }
                                                    >
                                                        {release.publishedAt ??
                                                            'Unscheduled'}
                                                    </time>
                                                </span>
                                            </div>

                                            <div className="h-px bg-foreground/5" />

                                            <div className="flex flex-wrap gap-2">
                                                {release.downloadLinks.map(
                                                    (link, index) => (
                                                        <Button
                                                            key={link.id}
                                                            asChild
                                                            variant="outline"
                                                            className={cn(
                                                                'h-10 border shadow-none',
                                                                downloadButtonPalettes[
                                                                    index %
                                                                        downloadButtonPalettes.length
                                                                ],
                                                            )}
                                                        >
                                                            <a href={link.url}>
                                                                <Download data-icon="inline-start" />
                                                                {link.label ||
                                                                    (release
                                                                        .downloadLinks
                                                                        .length >
                                                                    1
                                                                        ? `Download ${index + 1}`
                                                                        : 'Download')}
                                                            </a>
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    </TabsContent>

                    <TabsContent value="screenshots">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {resource.screenshots.map((screenshot, index) => (
                                <div
                                    key={screenshot}
                                    className="relative aspect-video overflow-hidden rounded-md bg-muted ring-1 ring-foreground/10"
                                >
                                    {!loadedScreenshots.has(screenshot) ? (
                                        <Skeleton className="absolute inset-0 rounded-none" />
                                    ) : null}
                                    <img
                                        src={screenshot}
                                        alt={`${resource.title} screenshot ${index + 1}`}
                                        className={cn(
                                            'size-full object-cover transition-opacity duration-200',
                                            loadedScreenshots.has(screenshot)
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                        loading="lazy"
                                        decoding="async"
                                        referrerPolicy="no-referrer"
                                        onLoad={() =>
                                            markScreenshotLoaded(screenshot)
                                        }
                                        onError={() =>
                                            markScreenshotLoaded(screenshot)
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </SiteLayout>
    );
}
