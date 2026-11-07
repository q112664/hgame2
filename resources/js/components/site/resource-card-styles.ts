import { cn } from '@/lib/utils';

/** Translucent label chips over resource card thumbnails (category, platform, language, version). */
export const overlayChipClassName = cn(
    'inline-flex h-5 max-w-full items-center justify-center gap-0 rounded-[4px] px-1.5',
    'bg-black/50 text-[11px] leading-none font-semibold whitespace-nowrap text-white/90 backdrop-blur-[3px]',
);

/** Category keeps the accent fill so it reads before the technical chips. */
export const overlayCategoryChipClassName = cn(
    overlayChipClassName,
    'bg-primary/90 text-primary-foreground uppercase',
);

/** Square icon chip used for platforms in the thumbnail overlay. */
export const overlayIconChipClassName = cn(overlayChipClassName, 'w-5 px-0');

/** Primary title in the card body — full contrast, catalog-scan friendly. */
export const resourceCardTitleClassName = cn(
    'line-clamp-2 min-h-[2.75em] min-w-0 font-heading text-[0.875rem] leading-[1.375] font-normal tracking-tight text-foreground',
);

/** Optional subtitle under the title — quieter, still readable. */
export const resourceCardSubtitleClassName = cn(
    'line-clamp-2 text-[13px] leading-relaxed text-muted-foreground',
);

/** Date / views footer row. */
export const resourceCardMetaClassName = cn(
    'text-xs text-muted-foreground tabular-nums',
);

/**
 * Download freshness cue for the card meta line. Deliberately chromeless so the
 * extra date reads as part of the date line instead of a second badge.
 */
export const resourceCardUpdateBadgeClassName = cn(
    'inline-flex h-5 w-fit shrink-0 items-center gap-1',
    'text-[11px] leading-none font-medium text-info/90',
);
