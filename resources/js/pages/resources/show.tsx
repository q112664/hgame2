import { Head } from '@inertiajs/react';
import { Download, Heart } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useLayoutEffect, useRef, useState } from 'react';
import { PlatformIcon } from '@/components/site/platform-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { MockResourceDetail } from '@/data/mock-resources';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';

type Props = {
    resource: MockResourceDetail;
};

type ResourceTab = 'details' | 'downloads' | 'screenshots';

const resourceTabs: Array<{ value: ResourceTab; label: string }> = [
    { value: 'details', label: 'Details' },
    { value: 'downloads', label: 'Downloads' },
    { value: 'screenshots', label: 'Screenshots' },
];

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

export default function ResourceShow({ resource }: Props) {
    const shouldReduceMotion = useReducedMotion();
    const [activeTab, setActiveTab] = useState<ResourceTab>('details');
    const [isFavorite, setIsFavorite] = useState(false);
    const tabsListRef = useRef<HTMLDivElement>(null);
    const tabRefs = useRef<
        Partial<Record<ResourceTab, HTMLButtonElement | null>>
    >({});
    const [pill, setPill] = useState({ left: 0, width: 0, ready: false });

    useLayoutEffect(() => {
        const updatePill = () => {
            const list = tabsListRef.current;
            const activeTrigger = tabRefs.current[activeTab];

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
        window.addEventListener('resize', updatePill);

        return () => window.removeEventListener('resize', updatePill);
    }, [activeTab]);

    const showDownloads = () => {
        setActiveTab('downloads');

        requestAnimationFrame(() => {
            tabsListRef.current?.scrollIntoView({
                behavior: shouldReduceMotion ? 'auto' : 'smooth',
                block: 'start',
            });
            tabRefs.current.downloads?.focus({ preventScroll: true });
        });
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
                                <Badge variant="outline">
                                    <PlatformIcon
                                        platform={resource.platform}
                                        data-icon="inline-start"
                                    />
                                    {resource.platform}
                                </Badge>
                                <Badge variant="outline">
                                    {resource.language}
                                </Badge>
                                {resource.tags.map((tag) => (
                                    <Badge key={tag} variant="outline">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>

                            <h1 className="font-heading text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                                {resource.title}
                            </h1>

                            <p className="text-sm text-muted-foreground">
                                {resource.developer}
                            </p>

                            <div className="mt-auto flex items-center gap-2 pt-1">
                                <Button size="lg" onClick={showDownloads}>
                                    <Download data-icon="inline-start" />
                                    Download
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

                <Tabs
                    value={activeTab}
                    onValueChange={(value) =>
                        setActiveTab(value as ResourceTab)
                    }
                    className="gap-4"
                >
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
                                              type: 'spring',
                                              stiffness: 420,
                                              damping: 34,
                                          }
                                }
                            />
                        ) : null}
                        {resourceTabs.map((tab) => (
                            <TabsTrigger
                                key={tab.value}
                                value={tab.value}
                                ref={(node) => {
                                    tabRefs.current[tab.value] = node;
                                }}
                                className={tabTriggerClassName}
                            >
                                {tab.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="details">
                        <div className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                            <h2 className="mb-3 font-heading text-base font-semibold text-foreground">
                                About
                            </h2>
                            <p className="mb-5 text-sm leading-relaxed text-muted-foreground">
                                {resource.description}
                            </p>

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
                                    value={resource.releaseDate}
                                />
                                <MetaRow
                                    label="Published"
                                    value={resource.publishedAt}
                                />
                                <MetaRow
                                    label="Platform"
                                    value={resource.platform}
                                />
                                <MetaRow
                                    label="Language"
                                    value={resource.language}
                                />
                                <MetaRow
                                    label="File size"
                                    value={resource.fileSize}
                                />
                                <MetaRow
                                    label="Views"
                                    value={formatCount(resource.views)}
                                />
                                <MetaRow
                                    label="Downloads"
                                    value={formatCount(resource.downloads)}
                                />
                            </dl>
                        </div>
                    </TabsContent>

                    <TabsContent value="downloads">
                        <div className="flex flex-col gap-3">
                            {resource.downloadLinks.map((link) => {
                                return (
                                    <article
                                        key={link.label}
                                        className="rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5"
                                    >
                                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0 flex-1 space-y-3">
                                                <div className="space-y-1">
                                                    <h3 className="font-heading text-base font-semibold text-foreground">
                                                        {link.label}
                                                    </h3>
                                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                                        {link.description}
                                                    </p>
                                                    {link.note ? (
                                                        <p className="text-sm text-muted-foreground">
                                                            {link.note}
                                                        </p>
                                                    ) : null}
                                                </div>

                                                <div className="flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <PlatformIcon
                                                            platform={
                                                                link.platform
                                                            }
                                                            className="size-3.5"
                                                        />
                                                        {link.platform}
                                                    </span>
                                                    <span>{link.language}</span>
                                                    <span>{link.fileSize}</span>
                                                    <time
                                                        dateTime={
                                                            link.publishedAt
                                                        }
                                                    >
                                                        {link.publishedAt}
                                                    </time>
                                                </div>
                                            </div>

                                            <Button
                                                asChild
                                                className="shrink-0 self-start"
                                            >
                                                <a href={link.url}>
                                                    <Download data-icon="inline-start" />
                                                    Download
                                                </a>
                                            </Button>
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
                                    className="aspect-video overflow-hidden rounded-md bg-muted ring-1 ring-foreground/10"
                                >
                                    <img
                                        src={screenshot}
                                        alt={`${resource.title} screenshot ${index + 1}`}
                                        className="size-full object-cover"
                                        loading="lazy"
                                        referrerPolicy="no-referrer"
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
