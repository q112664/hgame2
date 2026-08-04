import { Link } from '@inertiajs/react';
import { ArrowRight, Library, Sparkles } from 'lucide-react';
import { ResourceCard } from '@/components/site/resource-card';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { Button } from '@/components/ui/button';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
    title: string;
    id?: string;
    emptyMessage?: string;
    dateField?: 'publishedAt' | 'releaseDate';
    viewAllHref?: string | null;
};

export function LatestResources({
    resources,
    title,
    id = 'latest',
    emptyMessage = 'No resources yet.',
    dateField = 'publishedAt',
    viewAllHref = resourcesIndex().url,
}: Props) {
    return (
        <section
            id={id}
            className="scroll-mt-16"
            aria-labelledby={`${id}-heading`}
        >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Sparkles
                            className="size-4 text-primary sm:size-5"
                            aria-hidden
                        />
                        <h2
                            id={`${id}-heading`}
                            className="font-heading text-lg font-semibold text-foreground sm:text-xl"
                        >
                            {title}
                        </h2>
                    </div>
                    {viewAllHref ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 border-border/80 bg-card px-2.5 text-sm font-medium shadow-none"
                            asChild
                        >
                            <Link href={viewAllHref} prefetch>
                                View all
                                <ArrowRight data-icon="inline-end" />
                            </Link>
                        </Button>
                    ) : null}
                </div>

                {resources.length > 0 ? (
                    <div className="mt-3 grid grid-cols-1 gap-3 sm:mt-3.5 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
                        {resources.map((resource, index) => (
                            <ResourceCard
                                key={resource.id}
                                resource={resource}
                                dateField={dateField}
                                priority={index < 4}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="mt-3 sm:mt-3.5">
                        <SiteEmptyState
                            icon={Library}
                            title={emptyMessage}
                            className="min-h-40 py-10"
                        />
                    </div>
                )}
            </div>
        </section>
    );
}
