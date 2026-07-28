import { Link } from '@inertiajs/react';
import { Dices, Library } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    index as resourcesIndex,
    random as resourcesRandom,
} from '@/routes/resources';

export type HomeHeroContent = {
    backgroundUrl: string;
    eyebrow: string;
    title: string;
    description: string;
    browseLabel: string;
    randomLabel: string;
    showBrowse: boolean;
    showRandom: boolean;
};

type Props = {
    hero: HomeHeroContent;
};

export function HomeHero({ hero }: Props) {
    const showButtons = hero.showBrowse || hero.showRandom;

    return (
        <section id="hero" className="scroll-mt-16" aria-label="Welcome">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    className={cn(
                        'relative overflow-hidden rounded-md',
                        'ring-1 ring-border/80 shadow-sm',
                        'min-h-[220px] sm:min-h-[260px]',
                    )}
                >
                    <img
                        src={hero.backgroundUrl}
                        alt=""
                        className="absolute inset-0 size-full object-cover object-center"
                        loading="eager"
                        decoding="async"
                    />

                    <div
                        className={cn(
                            'absolute inset-0',
                            'bg-gradient-to-r from-surface-inverse/88 via-surface-inverse/55 to-primary/25',
                            'dark:from-surface-inverse/92 dark:via-surface-inverse/65 dark:to-primary/20',
                        )}
                        aria-hidden
                    />
                    <div
                        className={cn(
                            'absolute inset-0',
                            'bg-gradient-to-t from-surface-inverse/50 via-transparent to-transparent',
                        )}
                        aria-hidden
                    />

                    <div className="relative flex min-h-[220px] flex-col justify-center gap-5 p-6 sm:min-h-[260px] sm:p-8 lg:max-w-xl lg:p-10">
                        <div className="flex flex-col gap-2.5">
                            {hero.eyebrow !== '' ? (
                                <p className="text-[11px] font-medium tracking-wide text-surface-inverse-foreground/70 uppercase">
                                    {hero.eyebrow}
                                </p>
                            ) : null}
                            <h1 className="font-heading text-3xl font-semibold tracking-tight text-surface-inverse-foreground sm:text-4xl">
                                {hero.title}
                            </h1>
                            {hero.description !== '' ? (
                                <p className="max-w-md text-sm leading-relaxed text-surface-inverse-foreground/80">
                                    {hero.description}
                                </p>
                            ) : null}
                        </div>

                        {showButtons ? (
                            <div className="flex flex-wrap gap-2.5">
                                {hero.showBrowse ? (
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        className={cn(
                                            'border-auth/45 bg-auth/22',
                                            'text-auth-foreground shadow-none backdrop-blur-sm',
                                            'hover:border-auth/60 hover:bg-auth/32',
                                            'hover:text-auth-foreground',
                                            'focus-visible:border-auth/65 focus-visible:ring-auth/30',
                                            'dark:border-auth/35 dark:bg-auth/18',
                                            'dark:hover:border-auth/50 dark:hover:bg-auth/28',
                                        )}
                                        asChild
                                    >
                                        <Link href={resourcesIndex()} prefetch>
                                            <Library data-icon="inline-start" />
                                            {hero.browseLabel}
                                        </Link>
                                    </Button>
                                ) : null}
                                {hero.showRandom ? (
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        className={cn(
                                            'border-surface-inverse-foreground/25 bg-surface-inverse-foreground/10',
                                            'text-surface-inverse-foreground shadow-none backdrop-blur-sm',
                                            'hover:border-surface-inverse-foreground/40 hover:bg-surface-inverse-foreground/18',
                                            'hover:text-surface-inverse-foreground',
                                            'focus-visible:border-surface-inverse-foreground/50 focus-visible:ring-surface-inverse-foreground/25',
                                            'dark:border-surface-inverse-foreground/20 dark:bg-surface-inverse-foreground/10',
                                            'dark:hover:border-surface-inverse-foreground/35 dark:hover:bg-surface-inverse-foreground/16',
                                        )}
                                        asChild
                                    >
                                        <Link href={resourcesRandom()}>
                                            <Dices data-icon="inline-start" />
                                            {hero.randomLabel}
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>
        </section>
    );
}
