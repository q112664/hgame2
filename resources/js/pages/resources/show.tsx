import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Apple,
    ArrowLeft,
    Download,
    Eye,
    HardDrive,
    Monitor,
    Smartphone,
    Terminal,
} from 'lucide-react';
import { ResourceCard } from '@/components/site/resource-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import type {
    MockResource,
    MockResourceDetail,
} from '@/data/mock-resources';
import { SiteLayout } from '@/layouts/site-layout';
import { home } from '@/routes';

type Props = {
    resource: MockResourceDetail;
    related: MockResource[];
};

const platformIcons: Record<string, LucideIcon> = {
    Windows: Monitor,
    Android: Smartphone,
    macOS: Apple,
    Linux: Terminal,
};

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-US').format(value);
}

export default function ResourceShow({ resource, related }: Props) {
    const PlatformIcon = platformIcons[resource.platform] ?? Monitor;

    return (
        <SiteLayout>
            <Head title={`${resource.title} - hgame`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="hover:bg-black/5 dark:hover:bg-white/10"
                        asChild
                    >
                        <Link href={home()}>
                            <ArrowLeft data-icon="inline-start" />
                            Back to home
                        </Link>
                    </Button>
                </div>

                <section className="overflow-hidden rounded-md bg-card ring-1 ring-foreground/10">
                    <div className="grid gap-0 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                        <div className="aspect-video overflow-hidden bg-muted lg:aspect-auto lg:min-h-80">
                            <img
                                src={resource.thumbnail}
                                alt={resource.title}
                                className="size-full object-cover"
                                referrerPolicy="no-referrer"
                            />
                        </div>

                        <div className="flex flex-col gap-5 p-5 sm:p-6">
                            <div className="flex flex-col gap-3">
                                <div className="flex flex-wrap gap-1.5">
                                    <Badge variant="secondary">
                                        {resource.category}
                                    </Badge>
                                    <Badge variant="outline">
                                        <PlatformIcon data-icon="inline-start" />
                                        {resource.platform}
                                    </Badge>
                                    <Badge variant="outline">
                                        {resource.language}
                                    </Badge>
                                    {resource.tags.map((tag) => (
                                        <Badge key={tag} variant="outline">
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>

                                <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                    {resource.title}
                                </h1>

                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {resource.description}
                                </p>
                            </div>

                            <Separator />

                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        Developer
                                    </dt>
                                    <dd className="font-medium text-foreground">
                                        {resource.developer}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        Release date
                                    </dt>
                                    <dd className="font-medium text-foreground">
                                        {resource.releaseDate}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        Published
                                    </dt>
                                    <dd className="font-medium text-foreground">
                                        {resource.publishedAt}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        File size
                                    </dt>
                                    <dd className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                        <HardDrive className="size-3.5 text-muted-foreground" />
                                        {resource.fileSize}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        Views
                                    </dt>
                                    <dd className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                        <Eye className="size-3.5 text-muted-foreground" />
                                        {formatNumber(resource.views)}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        Downloads
                                    </dt>
                                    <dd className="inline-flex items-center gap-1.5 font-medium text-foreground">
                                        <Download className="size-3.5 text-muted-foreground" />
                                        {formatNumber(resource.downloads)}
                                    </dd>
                                </div>
                            </dl>

                            <div className="mt-auto flex flex-wrap gap-2 pt-2">
                                <Button size="lg">
                                    <Download data-icon="inline-start" />
                                    Download
                                </Button>
                                <Button variant="outline" size="lg" asChild>
                                    <Link href={home()}>Browse more</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>

                {related.length > 0 && (
                    <section className="flex flex-col gap-6">
                        <div className="flex flex-col gap-2">
                            <h2 className="font-heading text-xl font-semibold tracking-tight text-foreground">
                                Related resources
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                More visual novels you might like
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {related.map((item) => (
                                <ResourceCard
                                    key={item.id}
                                    resource={item}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </SiteLayout>
    );
}
