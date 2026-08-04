import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Eye, Flame } from 'lucide-react';
import { useCallback, useRef } from 'react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { Button } from '@/components/ui/button';
import { formatViews } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { details as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
    title?: string;
    id?: string;
};

/**
 * Ranked horizontal strip for homepage “Popular”.
 * Native overflow scroll + optional prev/next buttons (no custom drag logic).
 */
export function PopularResources({
    resources,
    title = 'Popular',
    id = 'popular',
}: Props) {
    const scrollerRef = useRef<HTMLOListElement>(null);

    const scrollByCard = useCallback((direction: -1 | 1) => {
        const el = scrollerRef.current;

        if (el === null) {
            return;
        }

        const card = el.querySelector('li');
        const step = card instanceof HTMLElement ? card.offsetWidth + 12 : 200;

        el.scrollBy({ left: direction * step, behavior: 'smooth' });
    }, []);

    if (resources.length === 0) {
        return null;
    }

    return (
        <section
            id={id}
            className="scroll-mt-16"
            aria-labelledby={`${id}-heading`}
        >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Flame
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

                    <div className="hidden items-center gap-1 sm:flex">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon-sm"
                            className="border-border/80 bg-card shadow-none"
                            aria-label="Scroll popular left"
                            onClick={() => scrollByCard(-1)}
                        >
                            <ChevronLeft />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon-sm"
                            className="border-border/80 bg-card shadow-none"
                            aria-label="Scroll popular right"
                            onClick={() => scrollByCard(1)}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                </div>

                <ol
                    ref={scrollerRef}
                    className={cn(
                        'mt-3 flex gap-3 overflow-x-auto pb-2 sm:mt-3.5',
                        'snap-x snap-mandatory scroll-px-1',
                        'overscroll-x-contain',
                        // Styled native scrollbar (Firefox + WebKit).
                        '[scrollbar-width:thin] [scrollbar-color:color-mix(in_oklab,var(--muted-foreground)_35%,transparent)_transparent]',
                        '[&::-webkit-scrollbar]:h-1.5',
                        '[&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-transparent',
                        '[&::-webkit-scrollbar-thumb]:rounded-full',
                        '[&::-webkit-scrollbar-thumb]:bg-muted-foreground/30',
                        '[&::-webkit-scrollbar-thumb]:hover:bg-muted-foreground/50',
                        '[&::-webkit-scrollbar-thumb]:active:bg-muted-foreground/60',
                    )}
                >
                    {resources.map((resource, index) => {
                        const rank = index + 1;

                        return (
                            <li
                                key={resource.id}
                                className="w-[12rem] shrink-0 snap-start sm:w-[14rem]"
                            >
                                <Link
                                    href={resourceDetails(resource.id)}
                                    prefetch
                                    className={cn(
                                        'group relative block overflow-hidden rounded-lg',
                                        'aspect-[16/10] bg-muted ring-1 ring-border/70',
                                        'transition-[ring-color] duration-150',
                                        'hover:ring-foreground/15',
                                        'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                    )}
                                >
                                    <LazyThumbnail
                                        src={resource.thumbnail}
                                        alt={resource.title}
                                        priority={index < 3}
                                    />

                                    <div
                                        className={cn(
                                            'absolute inset-x-0 bottom-0',
                                            'bg-gradient-to-t from-black/85 via-black/40 to-transparent',
                                            'px-2 pt-6 pb-1.5 sm:px-2.5',
                                        )}
                                    >
                                        <p className="line-clamp-1 font-heading text-xs leading-tight font-semibold tracking-tight text-white sm:text-[13px]">
                                            {resource.title}
                                        </p>
                                        <p className="mt-0.5 inline-flex items-center gap-0.5 text-[10px] leading-none tabular-nums text-white/75">
                                            <Eye
                                                className="size-2.5 shrink-0 opacity-80"
                                                aria-hidden
                                            />
                                            {formatViews(resource.views)}
                                        </p>
                                    </div>

                                    <span
                                        className={cn(
                                            'absolute top-2 left-2 inline-flex size-7 items-center justify-center',
                                            'rounded-md text-xs font-bold tabular-nums shadow-sm',
                                            rank === 1 &&
                                                'bg-primary text-primary-foreground',
                                            rank === 2 &&
                                                'bg-foreground/85 text-background',
                                            rank === 3 &&
                                                'bg-warning/90 text-warning-foreground',
                                            rank > 3 &&
                                                'bg-black/55 text-white ring-1 ring-white/15 backdrop-blur-[2px]',
                                        )}
                                        aria-label={`Rank ${rank}`}
                                    >
                                        {rank}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ol>
            </div>
        </section>
    );
}
