import { useCallback, useState } from 'react';

/**
 * Tracks the loaded URL and also handles images that finish before hydration
 * binds native load events.
 */
export function useImageLoadState(src: string) {
    const [loadedSrc, setLoadedSrc] = useState<string | null>(null);

    const markLoaded = useCallback(() => {
        setLoadedSrc(src);
    }, [src]);

    const imageRef = useCallback(
        (node: HTMLImageElement | null) => {
            if (node?.complete) {
                setLoadedSrc(src);
            }
        },
        [src],
    );

    return {
        imageRef,
        loaded: loadedSrc === src,
        markLoaded,
    };
}
