import { Link } from '@inertiajs/react';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { BookOpen } from 'lucide-react';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { show as docsShow } from '@/routes/docs';
import type { DocListItem } from '@/types/docs';

type Props = {
    docs: DocListItem[];
    pageSeo?: PageSeoData | null;
};

export default function DocsIndex({ docs, pageSeo }: Props) {
    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title="Articles" />

            <SitePageContainer className="gap-6 sm:gap-8">
                {docs.length > 0 ? (
                    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                                            'h-full gap-0 overflow-hidden py-0 transition-[ring-color] duration-150',
                                            'group-hover:ring-primary/25',
                                            'group-focus-visible:ring-2 group-focus-visible:ring-ring/50',
                                        )}
                                    >
                                        <div className="relative aspect-[16/10] overflow-hidden bg-muted">
                                            {doc.thumbnail ? (
                                                <img
                                                    src={doc.thumbnail}
                                                    alt=""
                                                    className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                                                    loading="lazy"
                                                    referrerPolicy="no-referrer"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center text-muted-foreground/50">
                                                    <BookOpen className="size-10" />
                                                </div>
                                            )}
                                        </div>
                                        <CardHeader className="gap-2 px-4 pt-4 pb-2">
                                            <CardTitle className="line-clamp-2 text-base leading-snug group-hover:text-primary">
                                                {doc.title}
                                            </CardTitle>
                                            {doc.excerpt ? (
                                                <CardDescription className="line-clamp-2 text-sm leading-relaxed">
                                                    {doc.excerpt}
                                                </CardDescription>
                                            ) : null}
                                        </CardHeader>
                                        {doc.publishedAt ? (
                                            <CardContent className="px-4 pt-0 pb-4 text-xs text-muted-foreground">
                                                <time
                                                    dateTime={doc.publishedAt}
                                                >
                                                    {formatDate(
                                                        doc.publishedAt,
                                                    )}
                                                </time>
                                            </CardContent>
                                        ) : null}
                                    </Card>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <SiteEmptyState
                        icon={BookOpen}
                        title="No articles yet"
                        description="Check back later for new guides and notes."
                    />
                )}
            </SitePageContainer>
        </SiteLayout>
    );
}
