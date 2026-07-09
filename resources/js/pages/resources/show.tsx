import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { MockResourceDetail } from '@/data/mock-resources';
import { SiteLayout } from '@/layouts/site-layout';
import { getPlatformIcon } from '@/lib/platform-icons';

type Props = {
    resource: MockResourceDetail;
};

export default function ResourceShow({ resource }: Props) {
    const PlatformIcon = getPlatformIcon(resource.platform);

    return (
        <SiteLayout>
            <Head title={`${resource.title} - hgame`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                <section className="h-[300px] max-h-[300px] overflow-hidden rounded-md bg-card ring-1 ring-foreground/10">
                    <div className="flex h-full">
                        <div className="aspect-video h-full shrink-0 overflow-hidden bg-muted">
                            <img
                                src={resource.thumbnail}
                                alt={resource.title}
                                className="size-full object-cover"
                                referrerPolicy="no-referrer"
                            />
                        </div>

                        <div className="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:p-5">
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
                            </div>

                            <h1 className="font-heading text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                                {resource.title}
                            </h1>

                            <p className="line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                                {resource.description}
                            </p>

                            <div className="mt-auto">
                                <Button size="lg">
                                    <Download data-icon="inline-start" />
                                    Download
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </SiteLayout>
    );
}
