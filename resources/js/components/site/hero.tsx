import { Link } from '@inertiajs/react';
import { ArrowRight, Sparkles } from 'lucide-react';
import { index as resourcesIndex } from '@/routes/resources';
import { cn } from '@/lib/utils';

const buttonBase = cn(
    'inline-flex h-11 items-center justify-center gap-2 rounded-md px-6 text-sm font-medium transition-[background-color,box-shadow,color]',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
    'disabled:pointer-events-none disabled:opacity-50',
);

const buttonPrimary = cn(
    buttonBase,
    'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90',
);

const buttonGhost = cn(
    buttonBase,
    'bg-transparent text-foreground hover:bg-muted',
);

export function Hero() {
    return (
        <section id="hero" className="scroll-mt-16">
            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                <div
                    className={cn(
                        'relative overflow-hidden rounded-xl bg-card p-8 ring-1 ring-border/80 sm:p-12 lg:p-16',
                        'bg-gradient-to-br from-primary/10 via-card to-card',
                    )}
                >
                    <div
                        className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-primary/20 blur-3xl"
                        aria-hidden
                    />
                    <div
                        className="pointer-events-none absolute -bottom-24 -left-24 size-72 rounded-full bg-info/10 blur-3xl"
                        aria-hidden
                    />

                    <div className="relative flex max-w-3xl flex-col gap-5">
                        <span
                            className={cn(
                                'inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1',
                                'bg-primary/10 text-xs font-medium text-primary ring-1 ring-primary/20',
                            )}
                        >
                            <Sparkles className="size-3.5" aria-hidden />
                            Galgame Resource Hub
                        </span>

                        <h1 className="font-heading text-3xl font-bold tracking-tight text-foreground sm:text-4xl lg:text-5xl">
                            Discover &amp; download galgame resources
                        </h1>

                        <p className="text-base leading-relaxed text-muted-foreground sm:text-lg">
                            Browse the latest releases, track updates, and curate your
                            favorites — all in one place.
                        </p>

                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            <Link href={resourcesIndex()} className={buttonPrimary}>
                                Browse resources
                                <ArrowRight className="size-4" aria-hidden />
                            </Link>
                            <Link href="/login" className={buttonGhost}>
                                Sign in
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}