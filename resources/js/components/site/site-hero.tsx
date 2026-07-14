import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index as resourcesIndex } from '@/routes/resources';

export function SiteHero() {
    return (
        <section className="border-b border-border/60">
            <div className="mx-auto flex max-w-7xl flex-col items-start gap-6 px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div className="flex max-w-2xl flex-col gap-3">
                    <h1 className="font-heading text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">
                        hgame
                    </h1>
                    <p className="text-base text-muted-foreground sm:text-lg">
                        Visual novel / galgame resource downloads
                    </p>
                </div>
                <Button asChild size="lg">
                    <Link href={resourcesIndex()}>Browse resources</Link>
                </Button>
            </div>
        </section>
    );
}
