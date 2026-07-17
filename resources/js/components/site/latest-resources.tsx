import { Link } from '@inertiajs/react';
import { ResourceCard } from '@/components/site/resource-card';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
};

export function LatestResources({ resources }: Props) {
    return (
        <section id="latest" className="scroll-mt-16">
            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-12 lg:px-8">
                <div className="flex items-baseline justify-between gap-4">
                    <h2 className="font-heading text-lg font-semibold text-foreground sm:text-xl">
                        New
                    </h2>
                    <Link
                        href={resourcesIndex()}
                        className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        View all
                    </Link>
                </div>

                {resources.length > 0 ? (
                    <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {resources.map((resource) => (
                            <ResourceCard
                                key={resource.id}
                                resource={resource}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="mt-6 text-sm text-muted-foreground">
                        No resources yet.
                    </p>
                )}
            </div>
        </section>
    );
}
