import { Link } from '@inertiajs/react';
import { Dices, Library } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    index as resourcesIndex,
    random as resourcesRandom,
} from '@/routes/resources';

export type HomeHeroContent = {
    backgroundUrl: string;
    title: string;
    description: string;
    browseLabel: string;
    randomLabel: string;
    showBrowse: boolean;
    showRandom: boolean;
};

type Props = {
    hero: HomeHeroContent;
    /** Optional panel rendered beside the hero on large screens. */
    sidePanel?: ReactNode;
};

export function HomeHero({ hero, sidePanel }: Props) {
    const showButtons = hero.showBrowse || hero.showRandom;
    const hasSidePanel = Boolean(sidePanel);

    const banner = (
        <div
            className={cn(
                'relative min-w-0 overflow-hidden rounded-md',
                // Soft edge in light; no bright rim in dark mode.
                'ring-1 ring-black/5 shadow-sm',
                'dark:ring-0 dark:shadow-none',
                'min-h-[168px] sm:min-h-[188px]',
                hasSidePanel && 'h-full lg:min-h-[220px]',
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

            <div
                className={cn(
                    'relative flex min-h-[168px] flex-col justify-center gap-3.5 p-4 sm:min-h-[188px] sm:gap-4 sm:p-5 lg:p-6',
                    hasSidePanel
                        ? 'h-full lg:min-h-[220px] lg:max-w-none'
                        : 'lg:max-w-xl',
                )}
            >
                <div className="flex flex-col gap-1.5 sm:gap-2">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-surface-inverse-foreground sm:text-3xl">
                        {hero.title}
                    </h1>
                    {hero.description !== '' ? (
                        <p className="max-w-md text-sm leading-snug text-surface-inverse-foreground/80 sm:leading-relaxed">
                            {hero.description}
                        </p>
                    ) : null}
                </div>

                {showButtons ? (
                    <div className="flex flex-wrap gap-2">
                        {hero.showBrowse ? (
                            <Button
                                size="default"
                                variant="outline"
                                className={cn(
                                    'h-9 border-auth/45 bg-auth/22',
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
                                size="default"
                                variant="outline"
                                className={cn(
                                    'h-9 border-surface-inverse-foreground/25 bg-surface-inverse-foreground/10',
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
    );

    return (
        <section id="hero" className="scroll-mt-16" aria-label="Welcome">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {hasSidePanel ? (
                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(220px,260px)] lg:items-stretch lg:gap-4">
                        {banner}
                        {sidePanel}
                    </div>
                ) : (
                    banner
                )}
            </div>
        </section>
    );
}
