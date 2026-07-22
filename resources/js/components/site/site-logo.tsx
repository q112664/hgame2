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
        <span className={cn('inline-flex items-center gap-2', className)}>
            {showImage ? (
                <img
                    src={siteLogo.imageUrl ?? undefined}
                    alt={showText ? '' : siteLogo.text}
                    className={cn(
                        'h-7 w-auto max-w-40 object-contain',
                        imageClassName,
                    )}
                    referrerPolicy="no-referrer"
                />
            ) : null}
            {showText ? (
                <span
                    className={cn(
                        'font-heading text-lg font-semibold tracking-tight text-foreground',
                        textClassName,
                    )}
                >
                    {siteLogo.text}
                </span>
            ) : null}
        </span>
    );
}
