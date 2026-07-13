import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { PlatformIcon } from '@/components/site/platform-icon';
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
} from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type FavoriteResource = GameCard & {
    hasDownloadUpdate: boolean;
};

type Props = {
    resources: FavoriteResource[];
    downloadUpdateCount: number;
};

const languageAbbrev: Record<string, string> = {
    Chinese: 'CN',
    Japanese: 'JP',
    English: 'EN',
};

function abbreviateLanguage(language: string): string {
    return languageAbbrev[language] ?? language.slice(0, 2).toUpperCase();
}

function abbreviateVersion(version: string): string {
    const withoutPrefix = version.trim().replace(/^(version|ver|v)\s*/i, '');
    const short = withoutPrefix.split(/\s+/)[0] ?? withoutPrefix;

    return short ? `v${short}` : version.trim();
}

function formatViews(views: number): string {
    return new Intl.NumberFormat('en-US').format(views);
}

function formatDate(date: string): string {
    const [year, month, day] = date.split('-');

    return `${year}-${month}-${day}`;
}

const metaChipClassName = cn(
    'inline-flex h-5 items-center gap-1 rounded px-1.5',
    'bg-muted text-[11px] font-medium leading-none text-muted-foreground',
);

export default function Favorites({
    resources,
    downloadUpdateCount,
}: Props) {
    return (
        <SiteLayout>
            <Head title="Favorites" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
                <div className="flex items-baseline justify-between gap-3">
                    <h1 className="font-heading text-xl font-semibold tracking-tight text-foreground">
                        Favorites
                    </h1>
                    {resources.length > 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {resources.length}
                        </p>
                    ) : null}
                </div>

                {downloadUpdateCount > 0 ? (
                    <p className="rounded-md bg-sky-500/10 px-3 py-2 text-sm text-sky-800 ring-1 ring-sky-500/15 dark:text-sky-200">
                        {downloadUpdateCount === 1
                            ? '1 favorite has updated downloads'
                            : `${downloadUpdateCount} favorites have updated downloads`}
                    </p>
                ) : null}

                {resources.length > 0 ? (
                    <ul className="divide-y divide-foreground/8">
                        {resources.map((resource) => (
                            <li key={resource.id}>
                                <Link
                                    href={
                                        resource.hasDownloadUpdate
                                            ? resourceDownloads(resource.id)
                                                  .url
                                            : resourceDetails(resource.id).url
                                    }
                                    className="group flex items-center gap-3.5 py-3.5 transition-opacity hover:opacity-80"
                                    prefetch
                                >
                                    <div className="relative aspect-video w-24 shrink-0 overflow-hidden rounded-md bg-muted sm:w-28">
                                        <img
                                            src={resource.thumbnail}
                                            alt=""
                                            className="size-full object-cover"
                                            loading="lazy"
                                            referrerPolicy="no-referrer"
                                        />
                                        {resource.hasDownloadUpdate ? (
                                            <span
                                                className="absolute top-1.5 right-1.5 size-2 rounded-full bg-sky-500 ring-2 ring-background"
                                                aria-hidden
                                            />
                                        ) : null}
                                    </div>

                                    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                        <div className="flex min-w-0 items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="line-clamp-1 text-sm font-medium leading-snug text-foreground">
                                                    {resource.title}
                                                </p>
                                                {resource.subtitle ? (
                                                    <p className="mt-0.5 line-clamp-1 text-xs leading-snug text-muted-foreground">
                                                        {resource.subtitle}
                                                    </p>
                                                ) : null}
                                            </div>
                                            {resource.hasDownloadUpdate ? (
                                                <span className="shrink-0 rounded-full bg-sky-500/12 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:text-sky-300">
                                                    Updated
                                                </span>
                                            ) : null}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1">
                                            <span className={metaChipClassName}>
                                                {resource.category}
                                            </span>
                                            {resource.version ? (
                                                <span
                                                    className={metaChipClassName}
                                                >
                                                    {abbreviateVersion(
                                                        resource.version,
                                                    )}
                                                </span>
                                            ) : null}
                                            {resource.platforms.map(
                                                (platform) => (
                                                    <Tooltip key={platform.slug}>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                className={cn(
                                                                    metaChipClassName,
                                                                    'size-5 justify-center px-0',
                                                                )}
                                                                aria-label={
                                                                    platform.name
                                                                }
                                                            >
                                                                <PlatformIcon
                                                                    slug={
                                                                        platform.slug
                                                                    }
                                                                    className="size-3"
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
                                                        className={metaChipClassName}
                                                    >
                                                        {abbreviateLanguage(
                                                            language,
                                                        )}
                                                    </span>
                                                ),
                                            )}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-muted-foreground">
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
                                            <span className="inline-flex items-center gap-1">
                                                <Eye className="size-3" />
                                                {formatViews(resource.views)}
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
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No favorites yet
                    </p>
                )}
            </div>
        </SiteLayout>
    );
}
