import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { downloadHeroButtonClassName } from '@/components/site/resource-detail-styles';
import { SitePageContainer } from '@/components/site/site-page-container';
import { TurnstileWidget } from '@/components/turnstile-widget';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { SiteLayout } from '@/layouts/site-layout';
import { continueMethod } from '@/routes/download-links';
import { downloads as resourceDownloads } from '@/routes/resources';

type Props = {
    resource: {
        id: string;
        title: string;
    };
    link: {
        id: number;
        label: string;
        url: string | null;
        host: string | null;
        requiresTurnstile: boolean;
    };
};

export default function DownloadLinkShow({ resource, link }: Props) {
    const { turnstile } = usePage().props;
    const showTurnstile = link.requiresTurnstile && Boolean(turnstile.siteKey);
    const [turnstileResetKey, setTurnstileResetKey] = useState(0);

    return (
        <SiteLayout>
            <Head title={`Download — ${resource.title}`} />

            <SitePageContainer className="max-w-lg gap-4 py-8 sm:py-10">
                <Card size="sm" className="gap-0 overflow-hidden py-0">
                    <CardHeader className="gap-1 border-b border-border/80 py-4">
                        <p className="text-xs font-medium text-muted-foreground">
                            External download
                        </p>
                        <CardTitle className="text-base leading-snug sm:text-lg">
                            {resource.title}
                        </CardTitle>
                    </CardHeader>

                    <CardContent className="py-4">
                        <p className="text-base font-semibold text-foreground">
                            {link.label}
                        </p>
                        {link.host ? (
                            <p className="mt-1.5 inline-flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground">
                                <ExternalLink
                                    className="size-3.5 shrink-0"
                                    aria-hidden
                                />
                                <span className="truncate">{link.host}</span>
                            </p>
                        ) : null}
                    </CardContent>

                    {showTurnstile && turnstile.siteKey ? (
                        <Form
                            action={continueMethod.url(link.id)}
                            method="post"
                            onError={() =>
                                setTurnstileResetKey((key) => key + 1)
                            }
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="border-t border-border/80 px-4 py-4 sm:px-6">
                                        <TurnstileWidget
                                            siteKey={turnstile.siteKey!}
                                            error={
                                                errors[
                                                    'cf-turnstile-response'
                                                ] as string | undefined
                                            }
                                            resetKey={turnstileResetKey}
                                        />
                                    </div>
                                    <CardFooter className="flex flex-col gap-2 border-t border-border/80 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                                        <Button
                                            variant="outline"
                                            className="w-full sm:w-auto"
                                            asChild
                                        >
                                            <Link
                                                href={resourceDownloads(
                                                    resource.id,
                                                )}
                                                prefetch
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            className={`${downloadHeroButtonClassName} w-full sm:w-auto`}
                                            disabled={processing}
                                        >
                                            <Download data-icon="inline-start" />
                                            {processing
                                                ? 'Opening…'
                                                : 'Open download'}
                                        </Button>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    ) : (
                        <CardFooter className="flex flex-col gap-2 border-t border-border/80 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <Button
                                variant="outline"
                                className="w-full sm:w-auto"
                                asChild
                            >
                                <Link
                                    href={resourceDownloads(resource.id)}
                                    prefetch
                                >
                                    Cancel
                                </Link>
                            </Button>
                            {link.url ? (
                                <Button
                                    variant="secondary"
                                    className={`${downloadHeroButtonClassName} w-full sm:w-auto`}
                                    asChild
                                >
                                    <a href={link.url}>
                                        <Download data-icon="inline-start" />
                                        Open download
                                    </a>
                                </Button>
                            ) : null}
                        </CardFooter>
                    )}
                </Card>
            </SitePageContainer>
        </SiteLayout>
    );
}
