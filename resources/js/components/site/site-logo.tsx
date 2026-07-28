import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type SiteLogoProps = {
    className?: string;
    imageClassName?: string;
    textClassName?: string;
};

export function SiteLogo({
    className,
    imageClassName,
    textClassName,
}: SiteLogoProps) {
    const { siteLogo } = usePage().props;
    const showImage =
        Boolean(siteLogo.imageUrl) &&
        (siteLogo.mode === 'image' || siteLogo.mode === 'both');
    const showText =
        siteLogo.mode === 'text' ||
        siteLogo.mode === 'both' ||
        !showImage;

    return (
        <span
            className={cn(
                'inline-flex h-8 max-h-8 items-center gap-1.5',
                className,
            )}
        >
            {showImage ? (
                <img
                    src={siteLogo.imageUrl ?? undefined}
                    alt={showText ? '' : siteLogo.text}
                    className={cn(
                        'h-8 w-auto max-w-36 object-contain',
                        showText && 'rounded-md',
                        imageClassName,
                    )}
                    referrerPolicy="no-referrer"
                />
            ) : null}
            {showText ? (
                <span
                    className={cn(
                        'font-heading text-xl leading-none font-semibold tracking-tight text-foreground',
                        textClassName,
                    )}
                >
                    {siteLogo.text}
                </span>
            ) : null}
        </span>
    );
}
