import { Link } from '@inertiajs/react';
import { ResourceCard } from '@/components/site/resource-card';
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
        <section id={id} className="scroll-mt-16">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex items-baseline justify-between gap-4">
                    <h2 className="font-heading text-lg font-semibold text-foreground sm:text-xl">
                        {title}
                    </h2>
                    {viewAllHref ? (
                        <Link
                            href={viewAllHref}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            View all
                        </Link>
                    ) : null}
                </div>

                {resources.length > 0 ? (
                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {resources.map((resource) => (
                            <ResourceCard
                                key={resource.id}
                                resource={resource}
                                dateField={dateField}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="mt-4 text-sm text-muted-foreground">
                        {emptyMessage}
                    </p>
                )}
            </div>
        </section>
    );
}
