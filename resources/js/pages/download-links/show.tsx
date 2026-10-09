import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { downloadHeroButtonClassName } from '@/components/site/resource-detail-styles';
import { SitePageContainer } from '@/components/site/site-page-container';
import { TurnstileWidget } from '@/components/turnstile-widget';
import { Button } from '@/components/ui/button';
import { useTurnstileGate } from '@/hooks/use-turnstile-gate';
import { SiteLayout } from '@/layouts/site-layout';
import { resourceTabHref } from '@/lib/resource-tabs';
import { cn } from '@/lib/utils';
import { continueMethod } from '@/routes/download-links';

type Props = {
    resource: {
        id: string;
        title: string;
        thumbnail: string;
        thumbnailFallback: string;
    };
    link: {
        id: number;
        label: string;
        url: string | null;
        host: string | null;
        requiresTurnstile: boolean;
    };
    pageSeo?: PageSeoData | null;
};

export default function DownloadLinkShow({ resource, link, pageSeo }: Props) {
    const { turnstile } = usePage().props;
    const showTurnstile = link.requiresTurnstile && Boolean(turnstile.siteKey);
    const turnstileGate = useTurnstileGate(showTurnstile);
    const hasThumbnail = resource.thumbnail.trim() !== '';
    const destinationName = link.label.trim() || 'Download';
    const namedDestination = destinationName !== 'Download';

    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title={`Download — ${resource.title}`} />

            <SitePageContainer className="max-w-md gap-0 px-4 py-6 sm:px-4 sm:py-10 lg:px-4">
                <article
                    className={cn(
                        'overflow-hidden rounded-xl border border-border/70 bg-card',
                        'shadow-sm dark:border-border/50',
                    )}
                >
                    <header className="relative aspect-[16/10] overflow-hidden bg-surface-inverse">
                        {hasThumbnail ? (
                            <LazyThumbnail
                                src={resource.thumbnail}
                                fallbackSrc={resource.thumbnailFallback}
                                alt=""
                                priority
                                className="absolute inset-0"
                            />
                        ) : null}
                        <div
                            className="absolute inset-0 bg-gradient-to-t from-surface-inverse from-12% via-surface-inverse/70 via-46% to-transparent"
                            aria-hidden
                        />
                        <div className="absolute inset-x-0 bottom-0 space-y-0.5 px-4 pt-12 pb-3.5">
                            <p className="text-xs font-medium text-surface-inverse-foreground/75">
                                External download
                            </p>
                            <h1 className="line-clamp-2 font-heading text-base font-semibold tracking-tight text-balance text-surface-inverse-foreground">
                                {resource.title}
                            </h1>
                        </div>
                    </header>

                    {/* Always POST continue so downloads are recorded server-side. */}
                    <Form
                        action={continueMethod.url(link.id)}
                        method="post"
                        onBefore={
                            showTurnstile ? turnstileGate.onBefore : undefined
                        }
                        onError={
                            showTurnstile ? turnstileGate.reset : undefined
                        }
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-4 px-4 py-4">
                                <div className="border-l-2 border-primary pl-3">
                                    <p className="font-heading text-lg font-semibold tracking-tight text-balance text-foreground">
                                        {destinationName}
                                    </p>
                                    {link.host ? (
                                        <p className="mt-0.5 truncate font-mono text-sm text-muted-foreground">
                                            {link.host}
                                        </p>
                                    ) : null}
                                </div>

                                <p
                                    id="download-leave-note"
                                    className="text-sm leading-relaxed text-muted-foreground"
                                >
                                    Continue opens {destinationName} in this
                                    tab. The game page stays open in the other
                                    tab.
                                </p>

                                {showTurnstile && turnstile.siteKey ? (
                                    <div className="space-y-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Security check
                                        </p>
                                        <TurnstileWidget
                                            siteKey={turnstile.siteKey}
                                            error={
                                                errors[
                                                    'cf-turnstile-response'
                                                ] as string | undefined
                                            }
                                            resetKey={turnstileGate.resetKey}
                                            onTokenChange={
                                                turnstileGate.onTokenChange
                                            }
                                        />
                                    </div>
                                ) : null}

                                <div className="flex flex-col gap-1.5">
                                    <Button
                                        type="submit"
                                        className={cn(
                                            downloadHeroButtonClassName,
                                            'h-10 w-full min-w-0',
                                        )}
                                        disabled={
                                            processing ||
                                            (showTurnstile
                                                ? turnstileGate.submitDisabled
                                                : false)
                                        }
                                        title={
                                            showTurnstile
                                                ? turnstileGate.submitTitle
                                                : undefined
                                        }
                                        aria-describedby="download-leave-note"
                                    >
                                        <ExternalLink data-icon="inline-start" />
                                        <span className="truncate">
                                            {processing
                                                ? 'Opening…'
                                                : namedDestination
                                                  ? `Continue to ${destinationName}`
                                                  : 'Continue'}
                                        </span>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        className="h-9 w-full text-muted-foreground"
                                        asChild
                                    >
                                        <Link
                                            href={resourceTabHref(
                                                resource.id,
                                                'downloads',
                                            )}
                                            prefetch
                                        >
                                            <ArrowLeft data-icon="inline-start" />
                                            Back to downloads
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </article>
            </SitePageContainer>
        </SiteLayout>
    );
}
