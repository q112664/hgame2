import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Flame } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { Button } from '@/components/ui/button';
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

/** Pixels the pointer must travel before a press counts as a drag, not a click. */
const DRAG_THRESHOLD = 4;

/**
 * Ranked horizontal strip for homepage “Popular”.
 *
 * Scrolling stays native so touch devices keep the compositor-driven, hardware
 * accelerated swipe; the only extra is a 1:1 mouse drag, because a mouse cannot
 * swipe. No inertia, no snapping — pressing and moving moves the strip exactly as
 * far as the pointer moves.
 */
export function PopularResources({
    resources,
    title = 'Popular',
    id = 'popular',
}: Props) {
    const scrollerRef = useRef<HTMLOListElement>(null);
    const drag = useRef({ startX: 0, startScroll: 0, moved: false });
    const [edges, setEdges] = useState({ start: true, end: false });
    const [dragging, setDragging] = useState(false);
    /**
     * Classic scrollbars (Windows, Linux) take 11px of layout space *below* the
     * padding, while overlay scrollbars (macOS, iOS) float *inside* it. The same
     * padding therefore leaves the bar further from the cards on Windows, so the
     * padding compensates once the browser tells us which kind it draws.
     */
    const [overlayScrollbar, setOverlayScrollbar] = useState<boolean | null>(
        null,
    );

    const syncEdges = useCallback(() => {
        const element = scrollerRef.current;

        if (element === null) {
            return;
        }

        const max = element.scrollWidth - element.clientWidth;

        setEdges((previous) => {
            const next = {
                start: element.scrollLeft <= 1,
                end: max <= 1 || element.scrollLeft >= max - 1,
            };

            return previous.start === next.start && previous.end === next.end
                ? previous
                : next;
        });
    }, []);

    useEffect(() => {
        const element = scrollerRef.current;

        syncEdges();

        if (element === null) {
            return;
        }

        const measureScrollbar = () => {
            if (element.scrollWidth <= element.clientWidth) {
                return;
            }

            setOverlayScrollbar(
                element.offsetHeight - element.clientHeight === 0,
            );
        };

        measureScrollbar();

        element.addEventListener('scroll', syncEdges, { passive: true });
        window.addEventListener('resize', measureScrollbar);
        window.addEventListener('resize', syncEdges);

        return () => {
            element.removeEventListener('scroll', syncEdges);
            window.removeEventListener('resize', measureScrollbar);
            window.removeEventListener('resize', syncEdges);
        };
    }, [syncEdges, resources.length]);

    const scrollByCard = useCallback((direction: -1 | 1) => {
        const element = scrollerRef.current;

        if (element === null) {
            return;
        }

        const card = element.querySelector('li');
        const step = card instanceof HTMLElement ? card.offsetWidth + 12 : 200;

        element.scrollBy({ left: direction * step, behavior: 'smooth' });
    }, []);

    /**
     * Mouse only: touch and trackpad already scroll natively, and letting them run
     * through the browser is what keeps the strip smooth on a phone.
     */
    const onPointerDown = useCallback((event: React.PointerEvent) => {
        const element = scrollerRef.current;

        if (element === null || event.pointerType !== 'mouse') {
            return;
        }

        drag.current = {
            startX: event.clientX,
            startScroll: element.scrollLeft,
            moved: false,
        };

        setDragging(true);
    }, []);

    useEffect(() => {
        if (!dragging) {
            return;
        }

        const move = (event: PointerEvent) => {
            const element = scrollerRef.current;

            if (element === null) {
                return;
            }

            const distance = event.clientX - drag.current.startX;

            if (Math.abs(distance) > DRAG_THRESHOLD) {
                drag.current.moved = true;
            }

            element.scrollLeft = drag.current.startScroll - distance;
        };

        const stop = () => setDragging(false);

        // On the window: the pointer regularly leaves the strip mid-drag.
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
        window.addEventListener('pointercancel', stop);

        return () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);
            window.removeEventListener('pointercancel', stop);
        };
    }, [dragging]);

    const onClickCapture = useCallback((event: React.MouseEvent) => {
        if (!drag.current.moved) {
            return;
        }

        // A press that turned into a drag must not open the card it started on.
        event.preventDefault();
        event.stopPropagation();
        drag.current.moved = false;
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
                            disabled={edges.start}
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
                            disabled={edges.end}
                            onClick={() => scrollByCard(1)}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                </div>

                <ol
                    ref={scrollerRef}
                    className={cn(
                        'mt-3 flex gap-3 overflow-x-auto sm:mt-3.5',
                        // Overlay bars float inside the padding, classic ones sit below it.
                        // Tuned so both leave the same gap between cards and bar.
                        overlayScrollbar === false ? 'pb-1.5' : 'pb-4',
                        'overscroll-x-contain',
                        // Only the standard properties, so the bar stays the platform's
                        // own: thin and tinted where it is classic, overlay on macOS/iOS.
                        '[scrollbar-width:thin]',
                        '[scrollbar-color:color-mix(in_oklab,var(--muted-foreground)_40%,transparent)_transparent]',
                        // The grab cursor is the only hint that the strip can be dragged.
                        'sm:cursor-grab sm:active:cursor-grabbing',
                        dragging && 'select-none',
                    )}
                    onPointerDown={onPointerDown}
                    onClickCapture={onClickCapture}
                    // Cards are links, and links are natively draggable: without this a
                    // drag starts the browser's drag-and-drop and pointer events stop.
                    onDragStart={(event) => event.preventDefault()}
                >
                    {resources.map((resource, index) => {
                        const rank = index + 1;
                        const heat = formatViews(
                            resource.views + resource.downloads,
                        );

                        return (
                            <li
                                key={resource.id}
                                className="w-[14.5rem] shrink-0 sm:w-[16.5rem]"
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
                                        fallbackSrc={resource.thumbnailFallback}
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
                            </li>
                        );
                    })}
                </ol>
            </div>
        </section>
    );
}
