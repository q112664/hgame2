import { Link } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import { formatViews } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { show as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

export type PopularCard = GameCard & {
    downloads: number;
};

type Props = {
    resources: PopularCard[];
    title?: string;
    id?: string;
};

/**
 * Ranked horizontal strip for homepage “Popular”.
 *
 * Embla owns the scrolling: it brings pointer dragging, momentum and slide snapping
 * that a plain overflow container cannot. It scrolls with transforms, so the browser
 * scrollbar is gone by design and the arrows are the affordance — they read the
 * carousel's own state and disable themselves at either end.
 */
export function PopularResources({
    resources,
    title = 'Popular',
    id = 'popular',
}: Props) {
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
                <Carousel
                    options={{ align: 'start', containScroll: 'trimSnaps' }}
                >
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
                            <CarouselPrevious
                                size="icon-sm"
                                aria-label="Scroll popular left"
                                className="border-border/80 bg-card shadow-none"
                            />
                            <CarouselNext
                                size="icon-sm"
                                aria-label="Scroll popular right"
                                className="border-border/80 bg-card shadow-none"
                            />
                        </div>
                    </div>

                    <CarouselContent
                        className="mt-3 -ml-3 sm:mt-3.5"
                        role="list"
                    >
                        {resources.map((resource, index) => {
                            const rank = index + 1;
                            const heat = formatViews(
                                resource.views + resource.downloads,
                            );

                            return (
                                <CarouselItem
                                    key={resource.id}
                                    role="listitem"
                                    className="w-[14.5rem] pl-3 sm:w-[16.5rem]"
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
                                            fallbackSrc={
                                                resource.thumbnailFallback
                                            }
                                            alt={resource.title}
                                            priority={index < 3}
                                        />

                                        <div
                                            className={cn(
                                                'absolute inset-x-0 bottom-0',
                                                'bg-gradient-to-t from-black/85 via-black/45 to-transparent',
                                                'px-2.5 pt-8 pb-2 sm:px-3 sm:pb-2.5',
                                            )}
                                        >
                                            <p className="line-clamp-2 font-heading text-sm leading-snug font-semibold tracking-tight text-white">
                                                {resource.title}
                                            </p>
                                            <p
                                                className="mt-1 inline-flex items-center gap-1 text-xs leading-none font-semibold text-white tabular-nums"
                                                aria-label={`Heat ${heat}`}
                                            >
                                                <Flame
                                                    className="size-3.5 shrink-0 text-primary"
                                                    aria-hidden
                                                />
                                                {heat}
                                            </p>
                                        </div>

                                        <span
                                            className={cn(
                                                'absolute top-2 left-2 inline-flex size-8 items-center justify-center',
                                                'rounded-md text-sm font-bold tabular-nums shadow-sm',
                                                rank === 1 &&
                                                    'bg-primary text-primary-foreground',
                                                rank === 2 &&
                                                    'bg-foreground/85 text-background',
                                                rank === 3 &&
                                                    'bg-warning/90 text-warning-foreground',
                                                rank > 3 &&
                                                    'bg-black/60 text-white ring-1 ring-white/15',
                                            )}
                                            aria-label={`Rank ${rank}`}
                                        >
                                            {rank}
                                        </span>
                                    </Link>
                                </CarouselItem>
                            );
                        })}
                    </CarouselContent>
                </Carousel>
            </div>
        </section>
    );
}
