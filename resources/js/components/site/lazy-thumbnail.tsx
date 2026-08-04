import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    src: string;
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
 */
export function LazyThumbnail({
    src,
    alt,
    className,
    priority = false,
    fit = 'cover',
}: Props) {
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        setLoaded(false);
    }, [src]);

    return (
        <>
            {!loaded ? (
                <div
                    className="absolute inset-0 animate-pulse bg-muted"
                    aria-hidden="true"
                />
            ) : null}
            <img
                src={src}
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
                onLoad={() => setLoaded(true)}
                onError={() => setLoaded(true)}
            />
        </>
    );
}
