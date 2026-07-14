import { Link } from '@inertiajs/react';
import { ResourceCard } from '@/components/site/resource-card';
import { Button } from '@/components/ui/button';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
};

export function LatestResources({ resources }: Props) {
    return (
        <section id="latest" className="scroll-mt-16">
            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-12 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <h2 className="font-heading text-2xl font-semibold tracking-tight text-foreground">
                            Latest resources
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Recently updated visual novels and galgame resources
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="border-foreground/10 bg-card shadow-none"
                    >
                        <Link href={resourcesIndex()}>View all</Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {resources.map((resource) => (
                        <ResourceCard key={resource.id} resource={resource} />
                    ))}
                </div>
            </div>
        </section>
    );
}
