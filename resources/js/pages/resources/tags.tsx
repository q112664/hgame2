import { Link } from '@inertiajs/react';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import { tag as resourcesTag } from '@/routes/resources';

export type TagDirectoryItem = {
    name: string;
    value: string;
    count: number;
};

type Props = {
    tags: TagDirectoryItem[];
    pageSeo?: PageSeoData | null;
};

const tagChipClassName = cn(
    'inline-flex h-9 max-w-full items-center gap-2 rounded-md px-3',
    'text-sm font-medium text-foreground no-underline',
    'bg-muted/70 transition-colors',
    'hover:bg-primary/10 hover:text-primary',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

export default function ResourcesTags({ tags, pageSeo }: Props) {
    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title="Game Tags" />

            <SitePageContainer className="gap-6 sm:gap-8">
                <header className="flex flex-col gap-1">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        Game Tags
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Browse titles by tag. Each link opens an indexable tag
                        page with matching games.
                    </p>
                </header>

                {tags.length === 0 ? (
                    <SiteEmptyState
                        title="No tags yet"
                        description="Tags appear here when published games are tagged."
                    />
                ) : (
                    <section
                        aria-labelledby="tags-directory-heading"
                        className="rounded-md border border-border/80 bg-card p-4 sm:p-5"
                    >
                        <h2
                            id="tags-directory-heading"
                            className="mb-4 font-heading text-base font-semibold tracking-tight text-foreground sm:text-lg"
                        >
                            All tags
                            <span className="ml-2 text-sm font-normal text-muted-foreground tabular-nums">
                                {tags.length}
                            </span>
                        </h2>
                        <ul className="flex flex-wrap gap-2">
                            {tags.map((tag) => (
                                <li key={tag.value} className="min-w-0">
                                    <Link
                                        href={resourcesTag.url(tag.value)}
                                        className={tagChipClassName}
                                        prefetch
                                    >
                                        <span className="truncate">
                                            {tag.name}
                                        </span>
                                        <span className="shrink-0 text-xs font-normal text-muted-foreground tabular-nums">
                                            {tag.count}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </SitePageContainer>
        </SiteLayout>
    );
}
