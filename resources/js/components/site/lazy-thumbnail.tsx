import { useImageLoadState } from '@/hooks/use-image-load-state';
import { cn } from '@/lib/utils';

type Props = {
    src: string;
    fallbackSrc?: string;
    alt: string;
    className?: string;
    /**
     * First-screen / LCP candidates: load immediately instead of lazy.
     * Use sparingly (e.g. first row of a grid).
     */
    priority?: boolean;
    /** How the image fills its box. Prefer `contain` when the full art should stay visible. */
    fit?: 'cover' | 'contain';
};

/**
 * Card thumbnail with muted placeholder pulse + fade-in once the image loads.
 * Keeps native lazy-loading for off-screen cards.
 *
 * On SSR hard refresh, the browser may finish loading the image before React
 * hydrates and binds onLoad — a callback ref checks img.complete so we still
 * fade in. Tracking loadedSrc (not a boolean) means src changes naturally
 * reset to the unloaded state without a setState Effect.
 */
export function LazyThumbnail({
    src,
    fallbackSrc,
    alt,
    className,
    priority = false,
    fit = 'cover',
}: Props) {
    const {
        imageRef,
        loaded,
        markError,
        markLoaded,
        src: displaySrc,
    } = useImageLoadState(src, fallbackSrc);

    return (
        <>
            {!loaded ? (
                <div
                    className="absolute inset-0 animate-pulse bg-muted"
                    aria-hidden="true"
                />
            ) : null}
            <img
                ref={imageRef}
                src={displaySrc}
                alt={alt}
                className={cn(
                    'size-full transition-opacity duration-300 ease-out',
                    fit === 'contain' ? 'object-contain' : 'object-cover',
                    loaded ? 'opacity-100' : 'opacity-0',
                    className,
                )}
                loading={priority ? 'eager' : 'lazy'}
                decoding={priority ? 'sync' : 'async'}
                // fetchPriority is valid on HTMLImageElement; React types lag a bit.
                {...(priority ? { fetchPriority: 'high' as const } : {})}
                referrerPolicy="no-referrer"
                onLoad={markLoaded}
                onError={markError}
            />
        </>
    );
}
