import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FavoritesPagination } from '@/components/site/favorites-pagination';
import type { PaginatedFavorites } from '@/components/site/favorites-pagination';
import { PlatformIcon } from '@/components/site/platform-icon';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { SiteLayout } from '@/layouts/site-layout';
import {
    abbreviateLanguage,
    abbreviateVersion,
    formatDate,
    formatViews,
} from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import {
    details as resourceDetails,
    downloads as resourceDownloads,
} from '@/routes/resources';
import { destroy as destroyFavorite } from '@/routes/resources/favorite';
import type { GameCard } from '@/types/resources';

type FavoriteResource = GameCard & {
    hasDownloadUpdate: boolean;
};

type Props = {
    resources: PaginatedFavorites<FavoriteResource>;
    downloadUpdateCount: number;
};

const metaChipClassName = cn(
    'inline-flex h-6 items-center gap-1.5 rounded-md px-2',
    'bg-muted text-xs leading-none font-medium text-muted-foreground',
);

export default function Favorites({ resources, downloadUpdateCount }: Props) {
    const [removingId, setRemovingId] = useState<string | null>(null);

    const removeFavorite = (resourceId: string) => {
        if (removingId !== null) {
            return;
        }

        setRemovingId(resourceId);

        router.delete(destroyFavorite(resourceId).url, {
            preserveScroll: true,
            preserveState: true,
            only: ['resources', 'downloadUpdateCount'],
            onFinish: () => setRemovingId(null),
        });
    };

    return (
        <SiteLayout>
            <Head title="Favorites" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
                <div className="flex items-baseline justify-between gap-3">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground">
                        Favorites
                    </h1>
                    {resources.total > 0 ? (
                        <p className="text-base text-muted-foreground">
                            {resources.total}
                        </p>
                    ) : null}
                </div>

                {downloadUpdateCount > 0 ? (
                    <p className="rounded-md bg-sky-500/10 px-4 py-2.5 text-sm text-sky-800 ring-1 ring-sky-500/15 dark:text-sky-200">
                        {downloadUpdateCount === 1
                            ? '1 favorite has updated downloads'
                            : `${downloadUpdateCount} favorites have updated downloads`}
                    </p>
                ) : null}

                <div id="favorite-results" className="scroll-mt-20">
                    {resources.data.length > 0 ? (
                        <ul className="divide-y divide-foreground/8">
                            {resources.data.map((resource) => (
                                <li
                                    key={resource.id}
                                    className="flex items-center gap-2"
                                >
                                    <Link
                                        href={
                                            resource.hasDownloadUpdate
                                                ? resourceDownloads(resource.id)
                                                      .url
                                                : resourceDetails(resource.id)
                                                      .url
                                        }
                                        className="group flex min-w-0 flex-1 items-center gap-4 py-4 transition-opacity hover:opacity-80 sm:gap-5 sm:py-5"
                                        prefetch
                                    >
                                        <div className="relative aspect-video w-32 shrink-0 overflow-hidden rounded-md bg-muted sm:w-40">
                                            <img
                                                src={resource.thumbnail}
                                                alt={resource.title}
                                                className="size-full object-cover"
                                                loading="lazy"
                                                referrerPolicy="no-referrer"
                                            />
                                            {resource.hasDownloadUpdate ? (
                                                <span
                                                    className="absolute top-2 right-2 size-2.5 rounded-full bg-sky-500 ring-2 ring-background"
                                                    aria-hidden
                                                />
                                            ) : null}
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                                            <div className="flex min-w-0 items-start justify-between gap-3">
                                                <div className="min-w-0 space-y-1">
                                                    <p className="line-clamp-2 text-base leading-snug font-medium text-foreground sm:text-lg">
                                                        {resource.title}
                                                    </p>
                                                    {resource.subtitle ? (
                                                        <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                                                            {resource.subtitle}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                {resource.hasDownloadUpdate ? (
                                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-sky-500/12 px-2.5 py-1 text-xs font-medium text-sky-700 dark:text-sky-300">
                                                        <Download className="size-3.5" />
                                                        Updated
                                                    </span>
                                                ) : null}
                                            </div>

                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span
                                                    className={
                                                        metaChipClassName
                                                    }
                                                >
                                                    {resource.category}
                                                </span>
                                                {resource.version ? (
                                                    <span
                                                        className={
                                                            metaChipClassName
                                                        }
                                                    >
                                                        {abbreviateVersion(
                                                            resource.version,
                                                        )}
                                                    </span>
                                                ) : null}
                                                {resource.platforms.map(
                                                    (platform) => (
                                                        <Tooltip
                                                            key={platform.slug}
                                                        >
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <span
                                                                    className={cn(
                                                                        metaChipClassName,
                                                                        'size-6 justify-center px-0',
                                                                    )}
                                                                    aria-label={
                                                                        platform.name
                                                                    }
                                                                >
                                                                    <PlatformIcon
                                                                        slug={
                                                                            platform.slug
                                                                        }
                                                                        className="size-3.5"
                                                                    />
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent side="bottom">
                                                                {platform.name}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ),
                                                )}
                                                {resource.languages.map(
                                                    (language) => (
                                                        <span
                                                            key={language}
                                                            className={
                                                                metaChipClassName
                                                            }
                                                        >
                                                            {abbreviateLanguage(
                                                                language,
                                                            )}
                                                        </span>
                                                    ),
                                                )}
                                            </div>

                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                                <span className="truncate">
                                                    {resource.developer}
                                                </span>
                                                <span
                                                    aria-hidden
                                                    className="text-foreground/20"
                                                >
                                                    ·
                                                </span>
                                                <time
                                                    dateTime={
                                                        resource.publishedAt ??
                                                        undefined
                                                    }
                                                >
                                                    {resource.publishedAt
                                                        ? formatDate(
                                                              resource.publishedAt,
                                                          )
                                                        : 'Unscheduled'}
                                                </time>
                                                <span
                                                    aria-hidden
                                                    className="text-foreground/20"
                                                >
                                                    ·
                                                </span>
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Eye className="size-3.5" />
                                                    {formatViews(
                                                        resource.views,
                                                    )}
                                                </span>
                                                {resource.tags.length > 0 ? (
                                                    <>
                                                        <span
                                                            aria-hidden
                                                            className="text-foreground/20"
                                                        >
                                                            ·
                                                        </span>
                                                        <span className="line-clamp-1 min-w-0">
                                                            {resource.tags
                                                                .slice(0, 4)
                                                                .map(
                                                                    (tag) =>
                                                                        `#${tag}`,
                                                                )
                                                                .join(' ')}
                                                        </span>
                                                    </>
                                                ) : null}
                                            </div>
                                        </div>
                                    </Link>

                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                className="mr-1 shrink-0 text-muted-foreground hover:text-destructive sm:mr-0"
                                                aria-label={`Remove ${resource.title} from favorites`}
                                                disabled={removingId !== null}
                                                onClick={() =>
                                                    removeFavorite(resource.id)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Remove favorite
                                        </TooltipContent>
                                    </Tooltip>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="py-8 text-center text-base text-muted-foreground">
                            {resources.total > 0
                                ? 'No favorites on this page'
                                : 'No favorites yet'}
                        </p>
                    )}

                    <div className="mt-6">
                        <FavoritesPagination resources={resources} />
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}
