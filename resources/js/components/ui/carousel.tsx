import * as React from 'react';
import useEmblaCarousel, {
    type UseEmblaCarouselType,
} from 'embla-carousel-react';
import { WheelGesturesPlugin } from 'embla-carousel-wheel-gestures';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type CarouselApi = UseEmblaCarouselType[1];
type CarouselOptions = Parameters<typeof useEmblaCarousel>[0];

type CarouselContextValue = {
    carouselRef: ReturnType<typeof useEmblaCarousel>[0];
    api: CarouselApi;
    scrollPrev: () => void;
    scrollNext: () => void;
    canScrollPrev: boolean;
    canScrollNext: boolean;
};

const CarouselContext = React.createContext<CarouselContextValue | null>(null);

function useCarousel(): CarouselContextValue {
    const context = React.useContext(CarouselContext);

    if (context === null) {
        throw new Error('useCarousel must be used within a <Carousel />');
    }

    return context;
}

function Carousel({
    options,
    className,
    children,
    ...props
}: React.ComponentProps<'div'> & { options?: CarouselOptions }) {
    /**
     * Embla scrolls with transforms, so a trackpad swipe over the strip does nothing
     * without this plugin: it feeds wheel deltas back into the carousel instead of
     * letting them fall through to the page.
     */
    const plugins = React.useMemo(() => [WheelGesturesPlugin()], []);

    const [carouselRef, api] = useEmblaCarousel(
        {
            align: 'start',
            containScroll: 'trimSnaps',
            // Free scrolling: with snapping on, a drag shorter than half a slide is
            // pulled back to where it started, which reads as the strip fighting the
            // pointer. Momentum still takes over when the drag is released.
            dragFree: true,
            // Embla's defaults wait 10px before a drag starts and settle slowly;
            // both read as lag on a strip this wide.
            dragThreshold: 5,
            duration: 20,
            ...options,
        },
        plugins,
    );
    const [canScrollPrev, setCanScrollPrev] = React.useState(false);
    const [canScrollNext, setCanScrollNext] = React.useState(false);

    const onSelect = React.useCallback((emblaApi: CarouselApi) => {
        if (emblaApi === undefined) {
            return;
        }

        setCanScrollPrev(emblaApi.canScrollPrev());
        setCanScrollNext(emblaApi.canScrollNext());
    }, []);

    React.useEffect(() => {
        if (api === undefined) {
            return;
        }

        onSelect(api);
        api.on('select', onSelect);
        api.on('reInit', onSelect);

        return () => {
            api.off('select', onSelect);
            api.off('reInit', onSelect);
        };
    }, [api, onSelect]);

    const scrollPrev = React.useCallback(() => api?.scrollPrev(), [api]);
    const scrollNext = React.useCallback(() => api?.scrollNext(), [api]);

    const value = React.useMemo(
        () => ({
            carouselRef,
            api,
            scrollPrev,
            scrollNext,
            canScrollPrev,
            canScrollNext,
        }),
        [carouselRef, api, scrollPrev, scrollNext, canScrollPrev, canScrollNext],
    );

    return (
        <CarouselContext.Provider value={value}>
            <div
                data-slot="carousel"
                className={cn('relative', className)}
                onKeyDownCapture={(event) => {
                    if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        scrollPrev();
                    }

                    if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        scrollNext();
                    }
                }}
                {...props}
            >
                {children}
            </div>
        </CarouselContext.Provider>
    );
}

function CarouselContent({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    const { carouselRef } = useCarousel();

    return (
        <div
            ref={carouselRef}
            data-slot="carousel-viewport"
            className={cn(
                'overflow-hidden',
                // Embla drags with pointer events; the cursor and touch-action are the
                // only hints it does not set itself.
                'cursor-grab active:cursor-grabbing',
                'touch-pan-y',
            )}
        >
            <div
                data-slot="carousel-content"
                className={cn('flex', className)}
                {...props}
            />
        </div>
    );
}

function CarouselItem({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="carousel-item"
            className={cn('shrink-0 grow-0', className)}
            {...props}
        />
    );
}

function CarouselPrevious({
    className,
    ...props
}: React.ComponentProps<typeof Button>) {
    const { scrollPrev, canScrollPrev } = useCarousel();

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            data-slot="carousel-previous"
            className={cn('rounded-full', className)}
            disabled={!canScrollPrev}
            onClick={scrollPrev}
            {...props}
        >
            <ChevronLeftIcon />
            <span className="sr-only">Previous</span>
        </Button>
    );
}

function CarouselNext({
    className,
    ...props
}: React.ComponentProps<typeof Button>) {
    const { scrollNext, canScrollNext } = useCarousel();

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            data-slot="carousel-next"
            className={cn('rounded-full', className)}
            disabled={!canScrollNext}
            onClick={scrollNext}
            {...props}
        >
            <ChevronRightIcon />
            <span className="sr-only">Next</span>
        </Button>
    );
}

export {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselPrevious,
    CarouselNext,
    useCarousel,
    type CarouselApi,
};
