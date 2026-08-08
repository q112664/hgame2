import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import {
    downloadHeroButtonClassName,
    releaseFooterClassName,
} from '@/components/site/resource-detail-styles';
import { SitePageContainer } from '@/components/site/site-page-container';
import { TurnstileWidget } from '@/components/turnstile-widget';
import { Button } from '@/components/ui/button';
import { useTurnstileGate } from '@/hooks/use-turnstile-gate';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import { continueMethod } from '@/routes/download-links';
import { downloads as resourceDownloads } from '@/routes/resources';

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

function ActionFooter({
    resourceId,
    primary,
}: {
    resourceId: string;
    primary: ReactNode;
}) {
    return (
        <div
            className={cn(
                releaseFooterClassName,
                'flex flex-col-reverse gap-2.5 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-5',
            )}
        >
            <Button
                variant="ghost"
                size="sm"
                className="h-10 w-full justify-center text-muted-foreground sm:w-auto sm:justify-start"
                asChild
            >
                <Link href={resourceDownloads(resourceId)} prefetch>
                    <ArrowLeft data-icon="inline-start" />
                    Back
                </Link>
            </Button>
            {primary}
        </div>
    );
}

function ContinueButton({
    processing = false,
    disabled = false,
    title,
    asChild = false,
    children,
}: {
    processing?: boolean;
    disabled?: boolean;
    title?: string;
    asChild?: boolean;
    children?: ReactNode;
}) {
    return (
        <Button
            type={asChild ? undefined : 'submit'}
            variant="secondary"
            className={cn(
                downloadHeroButtonClassName,
                'h-10 w-full sm:w-auto sm:min-w-36',
            )}
            disabled={disabled || processing}
            title={title}
            asChild={asChild}
        >
            {children ?? (
                <>
                    <Download data-icon="inline-start" />
                    {processing ? 'Opening…' : 'Continue'}
                </>
            )}
        </Button>
    );
}

export default function DownloadLinkShow({ resource, link, pageSeo }: Props) {
    const { turnstile } = usePage().props;
    const showTurnstile = link.requiresTurnstile && Boolean(turnstile.siteKey);
    const turnstileGate = useTurnstileGate(showTurnstile);
    const hasThumbnail = resource.thumbnail.trim() !== '';

    const identity = (
        <div className="flex items-center gap-3.5 p-4 sm:gap-4 sm:p-5">
            {hasThumbnail ? (
                <div
                    className={cn(
                        'relative aspect-[16/10] w-20 shrink-0 overflow-hidden',
                        'rounded-lg bg-muted ring-1 ring-border/50 sm:w-[5.5rem]',
                    )}
                >
                    <LazyThumbnail
                        src={resource.thumbnail}
                        fallbackSrc={resource.thumbnailFallback}
                        alt={resource.title}
                        priority
                    />
                </div>
            ) : (
                <div
                    className={cn(
                        'flex aspect-[16/10] w-20 shrink-0 items-center justify-center',
                        'rounded-lg bg-muted text-muted-foreground ring-1 ring-border/50 sm:w-[5.5rem]',
                    )}
                    aria-hidden
                >
                    <Download className="size-5 opacity-50" />
                </div>
            )}

            <div className="min-w-0 flex-1 space-y-1">
                <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                    External download
                </p>
                <h1 className="line-clamp-2 font-heading text-[0.95rem] leading-snug font-semibold tracking-tight text-foreground sm:text-base">
                    {resource.title}
                </h1>
            </div>
        </div>
    );

    const destination = (
        <div className="space-y-1.5">
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                Destination
            </p>
            <p className="text-base font-semibold tracking-tight text-foreground">
                {link.label}
            </p>
            {link.host ? (
                <p className="flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground">
                    <ExternalLink
                        className="size-3.5 shrink-0 opacity-60"
                        aria-hidden
                    />
                    <span className="truncate">{link.host}</span>
                </p>
            ) : null}
        </div>
    );

    const notice = (
        <p className="flex gap-2 text-xs leading-relaxed text-muted-foreground">
            <ShieldCheck
                className="mt-0.5 size-3.5 shrink-0 opacity-70"
                aria-hidden
            />
            <span>
                You are leaving this site to open a third-party host. Continue
                only if you trust the destination.
            </span>
        </p>
    );

    return (
        <SiteLayout>
            <PageSeo
                seo={pageSeo}
                title={`Download — ${resource.title}`}
            />

            <SitePageContainer className="max-w-md gap-0 py-8 sm:py-12">
                <div
                    className={cn(
                        'overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm',
                        'dark:border-border/50',
                    )}
                >
                    {identity}

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
                            <>
                                <div className="space-y-4 border-t border-border/60 px-4 py-4 sm:px-5 sm:py-5">
                                    {destination}
                                    {notice}
                                    {showTurnstile && turnstile.siteKey ? (
                                        <div className="space-y-2">
                                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                Security check
                                            </p>
                                            <TurnstileWidget
                                                siteKey={turnstile.siteKey}
                                                error={
                                                    errors[
                                                        'cf-turnstile-response'
                                                    ] as string | undefined
                                                }
                                                resetKey={
                                                    turnstileGate.resetKey
                                                }
                                                onTokenChange={
                                                    turnstileGate.onTokenChange
                                                }
                                            />
                                        </div>
                                    ) : null}
                                </div>

                                <ActionFooter
                                    resourceId={resource.id}
                                    primary={
                                        <ContinueButton
                                            processing={processing}
                                            disabled={
                                                showTurnstile
                                                    ? turnstileGate.submitDisabled
                                                    : false
                                            }
                                            title={
                                                showTurnstile
                                                    ? turnstileGate.submitTitle
                                                    : undefined
                                            }
                                        />
                                    }
                                />
                            </>
                        )}
                    </Form>
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
