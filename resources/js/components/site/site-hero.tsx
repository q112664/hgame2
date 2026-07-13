import { Button } from '@/components/ui/button';

export function SiteHero() {
    return (
        <section className="border-b border-border/60">
            <div className="mx-auto flex max-w-[90rem] flex-col items-start gap-6 px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div className="flex max-w-2xl flex-col gap-3">
                    <h1 className="font-heading text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">
                        hgame
                    </h1>
                    <p className="text-base text-muted-foreground sm:text-lg">
                        Visual novel / galgame resource downloads
                    </p>
                </div>
                <Button asChild size="lg">
                    <a href="#latest">Browse latest</a>
                </Button>
            </div>
        </section>
    );
}
