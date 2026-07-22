import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Clock, ListTree } from 'lucide-react';
import { RichHtml } from '@/components/site/rich-html';
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
import { Separator } from '@/components/ui/separator';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { index as docsIndex, show as docsShow } from '@/routes/docs';
import type { DocArticle, DocListItem } from '@/types/docs';

type Props = {
    doc: DocArticle;
    related: DocListItem[];
};

export default function DocsShow({ doc, related }: Props) {
    return (
        <SiteLayout>
            <Head title={`${doc.title} - Docs`} />

            <SitePageContainer className="gap-8">
                <div className="flex flex-col gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2 w-fit text-muted-foreground hover:text-foreground"
                        asChild
                    >
                        <Link href={docsIndex()} prefetch>
                            <ArrowLeft className="size-3.5" />
                            All docs
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{doc.category}</Badge>
                            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Clock className="size-3.5" />
                                {doc.readingMinutes} min read
                            </span>
                        </div>
                        <h1 className="max-w-3xl font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            {doc.title}
                        </h1>
                        <p className="max-w-3xl text-sm leading-relaxed text-muted-foreground">
                            {doc.excerpt}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Published {formatDate(doc.publishedAt)}
                            {doc.updatedAt !== doc.publishedAt
                                ? ` · Updated ${formatDate(doc.updatedAt)}`
                                : null}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <article className="min-w-0 rounded-md border border-border bg-card p-5 sm:p-7">
                        <RichHtml
                            html={doc.body}
                            className="prose-headings:scroll-mt-24 prose-h2:mt-8 prose-h2:border-b prose-h2:border-border/80 prose-h2:pb-2 prose-h2:text-xl"
                        />
                    </article>

                    <aside className="lg:sticky lg:top-20 lg:self-start">
                        <div className="rounded-md border border-border bg-card p-4">
                            <div className="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                                <ListTree className="size-4 text-muted-foreground" />
                                On this page
                            </div>
                            {doc.headings.length > 0 ? (
                                <nav aria-label="Table of contents">
                                    <ul className="flex flex-col gap-1">
                                        {doc.headings.map((heading) => (
                                            <li key={heading.id}>
                                                <a
                                                    href={`#${heading.id}`}
                                                    className={cn(
                                                        'block rounded-sm px-2 py-1.5 text-sm text-muted-foreground',
                                                        'transition-colors hover:bg-foreground/5 hover:text-foreground',
                                                        'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                                    )}
                                                >
                                                    {heading.title}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </nav>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No sections
                                </p>
                            )}
                        </div>
                    </aside>
                </div>

                {related.length > 0 ? (
                    <section className="flex flex-col gap-4">
                        <Separator />
                        <div className="flex items-center gap-2">
                            <BookOpen className="size-4 text-muted-foreground" />
                            <h2 className="font-heading text-base font-semibold text-foreground">
                                Related docs
                            </h2>
                        </div>
                        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {related.map((item) => (
                                <li key={item.slug}>
                                    <Link
                                        href={docsShow(item.slug)}
                                        className="group block h-full focus-visible:outline-none"
                                        prefetch
                                    >
                                        <Card
                                            size="sm"
                                            className="h-full transition-[ring-color] group-hover:ring-primary/25 group-focus-visible:ring-2 group-focus-visible:ring-ring/50"
                                        >
                                            <CardHeader className="gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className="w-fit"
                                                >
                                                    {item.category}
                                                </Badge>
                                                <CardTitle className="line-clamp-2 text-sm leading-snug group-hover:text-primary">
                                                    {item.title}
                                                </CardTitle>
                                                <CardDescription className="line-clamp-2 text-xs">
                                                    {item.excerpt}
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="pt-0 text-[11px] text-muted-foreground">
                                                {item.readingMinutes} min
                                            </CardContent>
                                        </Card>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
            </SitePageContainer>
        </SiteLayout>
    );
}
