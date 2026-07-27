import { cn } from '@/lib/utils';

/** Frosted label chips over resource card thumbnails. */
export const overlayChipClassName = cn(
    'inline-flex h-5 max-w-full items-center justify-center rounded-sm px-1.5',
    'bg-black/40 text-[11px] leading-none font-medium text-white/90',
    'ring-1 ring-white/15 backdrop-blur-[2px]',
);

/**
 * Shared shell for fused overlay chip groups (e.g. multiple languages).
 * Items sit flush with hairline dividers — one frosted pill, not separate chips.
 */
export const overlayChipGroupClassName = cn(
    'inline-flex h-5 max-w-full items-stretch overflow-hidden rounded-sm',
    'bg-black/40 text-[11px] leading-none font-medium text-white/90',
    'ring-1 ring-white/15 backdrop-blur-[2px]',
);

export const overlayChipGroupItemClassName = cn(
    'inline-flex items-center justify-center px-1.5',
);

export const overlayChipGroupDividerClassName = cn(
    'border-l border-white/20',
);

/** Primary title in the card body — full contrast, catalog-scan friendly. */
export const resourceCardTitleClassName = cn(
    'line-clamp-2 min-w-0 font-heading text-[0.9375rem] leading-snug font-semibold tracking-tight text-foreground',
);

/** Optional subtitle under the title — quieter, still readable. */
export const resourceCardSubtitleClassName = cn(
    'line-clamp-2 text-[13px] leading-relaxed text-muted-foreground',
);

/** Date / views footer row. */
export const resourceCardMetaClassName = cn(
    'text-xs tabular-nums text-muted-foreground',
);

/** Compact “Updated” status chip for download freshness. */
export const resourceCardUpdateBadgeClassName = cn(
    'inline-flex h-5 w-fit shrink-0 items-center gap-1 rounded-sm bg-info/12 px-1.5',
    'text-[11px] leading-none font-medium text-info',
);
