import { cn } from '@/lib/utils';

/** Frosted label chips over resource card thumbnails. */
export const overlayChipClassName = cn(
    'inline-flex h-5 max-w-full items-center justify-center rounded-sm px-1.5',
    'bg-black/40 text-[11px] leading-none font-medium text-white/90',
    'ring-1 ring-white/15 backdrop-blur-[2px]',
);
