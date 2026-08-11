import { cn } from '@/lib/utils';

/** Shared flat badge shell — no border, soft fill, compact type. */
export const heroBadgeClassName = cn(
    'h-6 gap-1 rounded-md border-0 px-2.5 text-xs font-medium no-underline shadow-none',
    'transition-[color,background-color,box-shadow] duration-150',
    '[&>svg]:size-3.5!',
);

/** Shared focus ring for linked hero chips. */
const heroBadgeFocusClassName = cn(
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

/**
 * Category — soft brand tint so the genre/type reads first among meta chips
 * (distinct from platform blue, language violet, size green).
 */
export const categoryBadgeClassName = cn(
    heroBadgeClassName,
    heroBadgeFocusClassName,
    'bg-primary/12 text-primary',
    'dark:bg-primary/18 dark:text-primary',
    // Linked chip: deepen fill, keep hue (overrides Badge outline [a]:hover).
    'hover:bg-primary/20 hover:text-primary',
    'dark:hover:bg-primary/28 dark:hover:text-primary',
    'active:bg-primary/24 dark:active:bg-primary/32',
);

/** Language — violet soft fill (distinct from platform blue / size green) */
export const languageBadgeClassName = cn(
    heroBadgeClassName,
    heroBadgeFocusClassName,
    'bg-language/15 text-language',
    'hover:bg-language/25 hover:text-language',
    'dark:hover:bg-language/30',
    'active:bg-language/28',
);

/** Clickable tags in the details tab — quiet surface, light primary hint on hover */
export const tagBadgeClassName = cn(
    'inline-flex h-7 w-fit shrink-0 items-center justify-center',
    'rounded-md px-3 text-sm font-medium no-underline',
    'bg-muted text-muted-foreground',
    'transition-[color,background-color] duration-150',
    'hover:bg-primary/10 hover:text-primary',
    'active:bg-primary/14 active:text-primary',
    'focus-visible:bg-primary/10 focus-visible:text-primary',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

const platformBadgeClassNames: Record<string, string> = {
    windows: cn(
        heroBadgeClassName,
        heroBadgeFocusClassName,
        'bg-info/15 text-info',
        'hover:bg-info/25 hover:text-info',
        'dark:hover:bg-info/30',
        'active:bg-info/28',
    ),
    ios: cn(
        heroBadgeClassName,
        heroBadgeFocusClassName,
        'bg-info/15 text-info',
        'hover:bg-info/25 hover:text-info',
        'dark:hover:bg-info/30',
        'active:bg-info/28',
    ),
    android: cn(
        heroBadgeClassName,
        heroBadgeFocusClassName,
        'bg-success/15 text-success',
        'hover:bg-success/25 hover:text-success',
        'dark:hover:bg-success/30',
        'active:bg-success/28',
    ),
};

export function platformBadgeClassName(slug: string): string {
    return (
        platformBadgeClassNames[slug.toLowerCase()] ??
        cn(
            heroBadgeClassName,
            heroBadgeFocusClassName,
            'bg-muted text-muted-foreground',
            'hover:bg-muted/80 hover:text-foreground',
            'active:bg-muted',
        )
    );
}

export const fileSizeBadgeClassName = cn(
    heroBadgeClassName,
    'bg-success/15 text-success',
);

/**
 * Compact download CTA for release link rows — matches contributor block height
 * (h-8 / avatar size-8) so the footer bar reads as one aligned strip.
 * Icon + fixed “Download” label (no host/netdisk name on the button).
 */
export const downloadButtonClassName = cn(
    'h-8 min-w-0 gap-1.5 border border-transparent bg-primary px-3 text-sm font-medium text-primary-foreground shadow-none',
    'hover:bg-primary/90',
    'dark:border-primary/40 dark:bg-primary/90',
    'dark:hover:border-primary/50 dark:hover:bg-primary',
);

/**
 * Like toggle beside download CTAs — same h-8 rhythm as the download button.
 * Idle: quiet muted chip. Active: primary soft fill + filled thumb.
 */
export const likeButtonClassName = cn(
    'h-8 min-w-0 gap-1.5 border-0 px-2.5 text-sm font-medium shadow-none',
    'bg-muted text-muted-foreground',
    'hover:bg-primary/10 hover:text-primary',
    'dark:bg-white/10 dark:text-muted-foreground',
    'dark:hover:bg-primary/20 dark:hover:text-primary',
    'disabled:opacity-60 dark:disabled:opacity-50',
);

export const likeButtonActiveClassName = cn(
    'h-8 min-w-0 gap-1.5 border-0 px-2.5 text-sm font-medium shadow-none',
    'bg-primary/12 text-primary',
    'hover:bg-primary/18 hover:text-primary',
    'dark:bg-primary/22 dark:text-primary',
    'dark:hover:bg-primary/30',
    'disabled:opacity-60 dark:disabled:opacity-50',
);

/** Release card footer strip under the title/description. */
export const releaseFooterClassName = cn(
    'border-t border-border/70 bg-muted/35',
    'dark:bg-muted/20',
);

/**
 * Author block + package actions.
 * Mobile: stacked (author above, actions below). sm+: single horizontal strip.
 */
export const releaseFooterInnerClassName = cn(
    'flex flex-col gap-3 px-4 py-3',
    'sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5',
);

/** Primary download CTA on resource hero / confirm pages. */
export const downloadHeroButtonClassName = cn(
    'h-9 border border-transparent bg-primary px-3.5 text-sm text-primary-foreground shadow-none',
    'hover:bg-primary/90',
    'dark:border-primary/40 dark:bg-primary/90',
    'dark:hover:border-primary/50 dark:hover:bg-primary',
);

/**
 * Shared idle shell for hero action icons (favorite, etc.).
 * Borderless; dark mode uses a lifted chip so it reads on bg-card.
 */
export const heroActionIdleClassName = cn(
    'border-0 bg-muted text-muted-foreground shadow-none ring-0',
    'hover:bg-muted/80 hover:text-foreground',
    // card ≈ surface-raised in dark — avoid matching it or the control vanishes
    'dark:bg-white/10 dark:text-muted-foreground',
    'dark:hover:bg-white/14 dark:hover:text-foreground',
);

/** Active favorite state on the hero action shell (no border). */
export const heroFavoriteActiveClassName = cn(
    'border-0 bg-favorite/12 text-favorite shadow-none ring-0',
    'hover:bg-favorite/18 hover:text-favorite',
    'dark:bg-favorite/22 dark:text-favorite',
    'dark:hover:bg-favorite/30 dark:hover:text-favorite',
);
