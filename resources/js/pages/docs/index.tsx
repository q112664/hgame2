import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, BookOpen, Clock } from 'lucide-react';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { SiteLayout } from '@/layouts/site-layout';
import { index as docsIndex, show as docsShow } from '@/routes/docs';
import type { DocListItem } from '@/types/docs';

type Props = {
    docs: DocListItem[];
    categories: string[];
    filters: {
        category: string | null;
    };
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

export default function DocsIndex({ docs, categories, filters }: Props) {
    const applyCategory = (category: string | null) => {
        router.get(
            docsIndex.url({
                query: category ? { category } : {},
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['docs', 'filters'],
            },
        );
    };

    return (
        <SiteLayout>
            <Head title="Docs" />

            <SitePageContainer className="gap-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <div className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <BookOpen className="size-4" />
                            Documentation
                        </div>
                        <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Docs
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Guides, account help, and publishing notes for
                            browsing and managing resources on hgame.
                        </p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {docs.length} article{docs.length === 1 ? '' : 's'}
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={filters.category === null ? 'default' : 'outline'}
                        onClick={() => applyCategory(null)}
                    >
                        All
                    </Button>
                    {categories.map((category) => (
                        <Button
                            key={category}
                            type="button"
                            size="sm"
                            variant={
                                filters.category === category
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => applyCategory(category)}
                        >
                            {category}
                        </Button>
                    ))}
                </div>

                {docs.length > 0 ? (
                    <ul className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {docs.map((doc) => (
                            <li key={doc.slug}>
                                <Link
                                    href={docsShow(doc.slug)}
                                    className="group block h-full focus-visible:outline-none"
                                    prefetch
                                >
                                    <Card
                                        size="sm"
                                        className={cn(
                                            'h-full transition-[ring-color] duration-150',
                                            'group-hover:ring-primary/25',
                                            'group-focus-visible:ring-2 group-focus-visible:ring-ring/50',
                                        )}
                                    >
                                        <CardHeader className="gap-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="secondary">
                                                    {doc.category}
                                                </Badge>
                                                <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                                    <Clock className="size-3" />
                                                    {doc.readingMinutes} min
                                                </span>
                                            </div>
                                            <CardTitle className="text-base leading-snug group-hover:text-primary">
                                                {doc.title}
                                            </CardTitle>
                                            <CardDescription className="line-clamp-2 text-sm leading-relaxed">
                                                {doc.excerpt}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="mt-auto flex items-center justify-between gap-3 pt-0 text-xs text-muted-foreground">
                                            <time dateTime={doc.updatedAt}>
                                                Updated {formatDate(doc.updatedAt)}
                                            </time>
                                            <span className="inline-flex items-center gap-1 text-foreground/80 transition-colors group-hover:text-primary">
                                                Read
                                                <ArrowRight className="size-3.5" />
                                            </span>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="rounded-md border border-dashed border-border bg-card px-6 py-16 text-center">
                        <p className="text-sm text-muted-foreground">
                            No docs in this category yet.
                        </p>
                    </div>
                )}
            </SitePageContainer>
        </SiteLayout>
    );
}
