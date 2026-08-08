import { useCallback, useState } from 'react';

/**
 * Tracks the loaded URL and also handles images that finish before hydration
 * binds native load events.
 */
export function useImageLoadState(src: string, fallbackSrc?: string) {
    const [loadedSrc, setLoadedSrc] = useState<string | null>(null);
    const [fallbackForSrc, setFallbackForSrc] = useState<string | null>(null);

    const usingFallback =
        fallbackForSrc === src && Boolean(fallbackSrc) && fallbackSrc !== src;
    const displaySrc = usingFallback ? fallbackSrc! : src;

    const markLoaded = useCallback(() => {
        setLoadedSrc(displaySrc);
    }, [displaySrc]);

    const markError = useCallback(() => {
        if (!usingFallback && fallbackSrc && fallbackSrc !== src) {
            setFallbackForSrc(src);
            setLoadedSrc(null);

            return;
        }

        setLoadedSrc(displaySrc);
    }, [displaySrc, fallbackSrc, src, usingFallback]);

    const imageRef = useCallback(
        (node: HTMLImageElement | null) => {
            if (node?.complete && node.naturalWidth > 0) {
                setLoadedSrc(displaySrc);
            }
        },
        [displaySrc],
    );

    return {
        imageRef,
        loaded: loadedSrc === displaySrc,
        src: displaySrc,
        markLoaded,
        markError,
    };
}
