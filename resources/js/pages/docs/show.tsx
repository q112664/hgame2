import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { RichHtml } from '@/components/site/rich-html';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Button } from '@/components/ui/button';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { index as docsIndex } from '@/routes/docs';
import type { DocArticle } from '@/types/docs';

type Props = {
    doc: DocArticle;
};

export default function DocsShow({ doc }: Props) {
    const hasThumbnail = Boolean(doc.thumbnail);

    return (
        <SiteLayout>
            <Head title={doc.title} />

            <SitePageContainer className="gap-6 sm:gap-8">
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-2 w-fit text-muted-foreground hover:text-foreground"
                    asChild
                >
                    <Link href={docsIndex()} prefetch>
                        <ArrowLeft className="size-3.5" />
                        All articles
                    </Link>
                </Button>

                <article className="overflow-hidden rounded-lg border border-border bg-card">
                    <header
                        className={cn(
                            'relative',
                            hasThumbnail
                                ? 'flex min-h-36 flex-col justify-end bg-muted sm:min-h-40'
                                : 'border-b border-border px-5 py-5 sm:px-6 sm:py-6',
                        )}
                    >
                        {hasThumbnail ? (
                            <>
                                <img
                                    src={doc.thumbnail!}
                                    alt=""
                                    className="absolute inset-0 size-full object-cover"
                                    referrerPolicy="no-referrer"
                                />
                                <div
                                    className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"
                                    aria-hidden
                                />
                            </>
                        ) : null}

                        <div
                            className={cn(
                                'relative flex flex-col gap-2',
                                hasThumbnail && 'px-5 pt-10 pb-4 sm:px-6 sm:pt-12 sm:pb-5',
                            )}
                        >
                            <h1
                                className={cn(
                                    'font-heading text-2xl font-semibold tracking-tight sm:text-3xl',
                                    hasThumbnail
                                        ? 'text-white drop-shadow-sm'
                                        : 'text-foreground',
                                )}
                            >
                                {doc.title}
                            </h1>
                            {doc.excerpt ? (
                                <p
                                    className={cn(
                                        'max-w-3xl text-sm leading-relaxed sm:text-base',
                                        hasThumbnail
                                            ? 'text-white/85'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {doc.excerpt}
                                </p>
                            ) : null}
                            {doc.publishedAt ? (
                                <p
                                    className={cn(
                                        'text-xs',
                                        hasThumbnail
                                            ? 'text-white/70'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    <time dateTime={doc.publishedAt}>
                                        {formatDate(doc.publishedAt)}
                                    </time>
                                </p>
                            ) : null}
                        </div>
                    </header>

                    <div className="p-5 sm:p-7">
                        <RichHtml html={doc.body} />
                    </div>
                </article>
            </SitePageContainer>
        </SiteLayout>
    );
}
