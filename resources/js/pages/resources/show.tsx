import { Head, Link, router } from '@inertiajs/react';
import { Building2, CalendarDays, Download, Heart } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { PlatformIcon } from '@/components/site/platform-icon';
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

function formatCount(value: number): string {
    return new Intl.NumberFormat('en-US').format(value);
}

function MetaRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[7rem_1fr] gap-3 border-b border-foreground/5 py-2.5 last:border-b-0 sm:grid-cols-[9rem_1fr]">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium text-foreground">{value}</dd>
        </div>
    );
}

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
    const platforms = resource.platforms.join(', ') || 'No platform';
    const languages = resource.languages.join(', ') || 'No language';
    const fileSizes = Array.from(
        new Set(
            resource.releases
                .map((release) => release.fileSize)
                .filter((size): size is string => size !== null),
        ),
    ).join(', ');

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
                                <Badge variant="secondary">
                                    {resource.category}
                                </Badge>
                                {resource.platforms.map((platform) => (
                                    <Badge key={platform} variant="outline">
                                        <PlatformIcon
                                            platform={platform}
                                            data-icon="inline-start"
                                        />
                                        {platform}
                                    </Badge>
                                ))}
                                {resource.languages.map((language) => (
                                    <Badge key={language} variant="outline">
                                        {language}
                                    </Badge>
                                ))}
                            </div>

                            <h1 className="font-heading text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                                {resource.title}
                            </h1>

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
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,7fr)_minmax(18rem,3fr)] lg:gap-6">
                            <section className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                                <h2 className="mb-3 font-heading text-base font-semibold text-foreground">
                                    About
                                </h2>
                                <div
                                    className="space-y-3 text-sm leading-relaxed text-muted-foreground [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_h2]:font-heading [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-foreground [&_h3]:font-heading [&_h3]:font-semibold [&_h3]:text-foreground [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                                    dangerouslySetInnerHTML={{
                                        __html: resource.description,
                                    }}
                                />
                            </section>

                            <div className="flex flex-col gap-4">
                                <section className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                                    <h2 className="mb-1 font-heading text-base font-semibold text-foreground">
                                        Information
                                    </h2>
                                    <dl className="text-sm">
                                        <MetaRow
                                            label="Developer"
                                            value={resource.developer}
                                        />
                                        <MetaRow
                                            label="Release date"
                                            value={resource.releaseDate ?? '—'}
                                        />
                                        <MetaRow
                                            label="Published"
                                            value={resource.publishedAt ?? '—'}
                                        />
                                        <MetaRow
                                            label="Platform"
                                            value={platforms}
                                        />
                                        <MetaRow
                                            label="Language"
                                            value={languages}
                                        />
                                        <MetaRow
                                            label="File size"
                                            value={fileSizes || '—'}
                                        />
                                        <MetaRow
                                            label="Views"
                                            value={formatCount(resource.views)}
                                        />
                                        <MetaRow
                                            label="Downloads"
                                            value={formatCount(
                                                resource.downloads,
                                            )}
                                        />
                                    </dl>
                                </section>

                                <section className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                                    <h2 className="mb-3 font-heading text-base font-semibold text-foreground">
                                        Tags
                                    </h2>
                                    <div className="flex flex-wrap gap-1.5">
                                        {resource.tags.map((tag) => (
                                            <Badge key={tag} variant="outline">
                                                {tag}
                                            </Badge>
                                        ))}
                                    </div>
                                </section>
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="downloads">
                        <div className="flex flex-col gap-3">
                            {resource.releases.map((release) => {
                                return (
                                    <article
                                        key={release.id}
                                        className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5"
                                    >
                                        <div className="space-y-4">
                                            <div className="min-w-0 space-y-3">
                                                <div className="space-y-1">
                                                    {release.title ? (
                                                        <h3 className="font-heading text-base font-semibold text-foreground">
                                                            {release.title}
                                                        </h3>
                                                    ) : null}
                                                    {release.description ? (
                                                        <div
                                                            className="space-y-2 text-sm leading-relaxed text-muted-foreground [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                                                            dangerouslySetInnerHTML={{
                                                                __html: release.description,
                                                            }}
                                                        />
                                                    ) : null}
                                                </div>

                                                <div className="flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                                                    {release.platforms.map(
                                                        (platform) => (
                                                            <span
                                                                key={platform}
                                                                className="inline-flex items-center gap-1.5"
                                                            >
                                                                <PlatformIcon
                                                                    platform={
                                                                        platform
                                                                    }
                                                                    className="size-3.5"
                                                                />
                                                                {platform}
                                                            </span>
                                                        ),
                                                    )}
                                                    <span>
                                                        {release.languages.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                    {release.fileSize ? (
                                                        <span>
                                                            {release.fileSize}
                                                        </span>
                                                    ) : null}
                                                    <time
                                                        dateTime={
                                                            release.publishedAt ??
                                                            undefined
                                                        }
                                                    >
                                                        {release.publishedAt ??
                                                            'Unscheduled'}
                                                    </time>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap gap-2">
                                                {release.downloadLinks.map(
                                                    (link, index) => (
                                                        <div key={link.id}>
                                                            <Button
                                                                asChild
                                                                variant="outline"
                                                            >
                                                                <a
                                                                    href={
                                                                        link.url
                                                                    }
                                                                >
                                                                    <Download data-icon="inline-start" />
                                                                    {release
                                                                        .downloadLinks
                                                                        .length >
                                                                    1
                                                                        ? `Download ${index + 1}`
                                                                        : 'Download'}
                                                                </a>
                                                            </Button>
                                                        </div>
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
