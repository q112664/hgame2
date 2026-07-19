import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, ShieldAlert } from 'lucide-react';
import { PlatformIcon } from '@/components/site/platform-icon';
import {
    platformBadgeClassName,
} from '@/components/site/resource-detail-styles';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { SiteLayout } from '@/layouts/site-layout';
import { downloads as resourceDownloads } from '@/routes/resources';

type Props = {
    resource: {
        id: string;
        title: string;
        subtitle: string | null;
        category: string;
    };
    release: {
        id: number;
        title: string | null;
        version: string | null;
        fileSize: string | null;
        platforms: Array<{ name: string; slug: string }>;
    };
    link: {
        id: number;
        label: string;
        url: string;
        host: string | null;
    };
};

export default function DownloadLinkShow({ resource, release, link }: Props) {
    return (
        <SiteLayout>
            <Head title={`Download — ${resource.title}`} />

            <SitePageContainer className="max-w-2xl gap-4 py-8 sm:gap-5 sm:py-10">
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-2 h-8 w-fit text-muted-foreground hover:text-foreground"
                    asChild
                >
                    <Link href={resourceDownloads(resource.id)} prefetch>
                        <ArrowLeft data-icon="inline-start" />
                        Back to downloads
                    </Link>
                </Button>

                <Card size="sm" className="gap-0 py-0">
                    <CardHeader className="gap-2.5 border-b border-border/80 py-4">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant="secondary">{resource.category}</Badge>
                            {release.version ? (
                                <span className="inline-flex h-5 items-center rounded-sm bg-muted px-1.5 font-mono text-[11px] text-muted-foreground">
                                    v{release.version}
                                </span>
                            ) : null}
                            {release.fileSize ? (
                                <span className="font-mono text-[11px] text-muted-foreground">
                                    {release.fileSize}
                                </span>
                            ) : null}
                        </div>

                        <div className="flex flex-col gap-1">
                            <CardTitle className="text-base leading-snug sm:text-lg">
                                {resource.title}
                            </CardTitle>
                            {resource.subtitle ? (
                                <CardDescription className="text-sm leading-relaxed">
                                    {resource.subtitle}
                                </CardDescription>
                            ) : null}
                        </div>

                        {release.platforms.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                                {release.platforms.map((platform) => (
                                    <Badge
                                        key={platform.slug}
                                        variant="outline"
                                        className={platformBadgeClassName(
                                            platform.slug,
                                        )}
                                    >
                                        <PlatformIcon
                                            slug={platform.slug}
                                            data-icon="inline-start"
                                        />
                                        {platform.name}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}
                    </CardHeader>

                    <CardContent className="flex flex-col gap-4 py-4">
                        <div className="flex items-start gap-2.5 rounded-md border border-warning/25 bg-warning/10 px-3 py-2.5 text-sm leading-relaxed text-warning">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                            <p>
                                You are leaving hgame for an external download
                                host. Verify the destination before continuing.
                            </p>
                        </div>

                        <div className="flex flex-col gap-1">
                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                Destination
                            </p>
                            <p className="font-heading text-base font-semibold leading-snug text-foreground">
                                {link.label}
                            </p>
                            {link.host ? (
                                <p className="mt-0.5 inline-flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
                                    <ExternalLink className="size-3.5 shrink-0" />
                                    {link.host}
                                </p>
                            ) : null}
                        </div>
                    </CardContent>

                    <CardFooter className="flex flex-col-reverse gap-2 border-t border-border/80 py-3.5 sm:flex-row sm:justify-end sm:gap-2.5">
                        <Button variant="outline" asChild>
                            <Link href={resourceDownloads(resource.id)} prefetch>
                                Cancel
                            </Link>
                        </Button>
                        <Button asChild>
                            <a
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Download data-icon="inline-start" />
                                Continue to download
                            </a>
                        </Button>
                    </CardFooter>
                </Card>
            </SitePageContainer>
        </SiteLayout>
    );
}
