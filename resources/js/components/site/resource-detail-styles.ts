import { cn } from '@/lib/utils';

/** Shared flat badge shell — no border, soft fill, compact type. */
export const heroBadgeClassName = cn(
    'h-6 gap-1 rounded-md border-0 px-2.5 text-xs font-medium shadow-none',
    '[&>svg]:size-3.5!',
);

/**
 * Category — soft brand tint so the genre/type reads first among meta chips
 * (distinct from platform blue, language violet, size green).
 */
export const categoryBadgeClassName = cn(
    heroBadgeClassName,
    'bg-primary/12 text-primary',
    'dark:bg-primary/18 dark:text-primary',
);

/** Language — violet soft fill (distinct from platform blue / size green) */
export const languageBadgeClassName = cn(
    heroBadgeClassName,
    'bg-language/15 text-language',
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
    windows: cn(heroBadgeClassName, 'bg-info/15 text-info'),
    ios: cn(heroBadgeClassName, 'bg-info/15 text-info'),
    android: cn(heroBadgeClassName, 'bg-success/15 text-success'),
};

export function platformBadgeClassName(slug: string): string {
    return (
        platformBadgeClassNames[slug.toLowerCase()] ??
        cn(heroBadgeClassName, 'bg-muted text-muted-foreground')
    );
}

export const fileSizeBadgeClassName = cn(
    heroBadgeClassName,
    'bg-success/15 text-success',
);

export const dateBadgeClassName = cn(
    heroBadgeClassName,
    'bg-muted text-muted-foreground',
);

/**
 * Compact download CTA for release link rows — matches meta badge height rhythm
 * (h-8) so the footer bar reads as one aligned strip.
 * Icon + fixed “Download” label (no host/netdisk name on the button).
 */
export const downloadButtonClassName = cn(
    'h-8 min-w-0 gap-1.5 border border-transparent bg-primary px-3.5 text-sm font-medium text-primary-foreground shadow-none',
    'hover:bg-primary/90',
    'dark:border-primary/40 dark:bg-primary/90',
    'dark:hover:border-primary/50 dark:hover:bg-primary',
);

/** Release card footer strip under the title/description. */
export const releaseFooterClassName = cn(
    'border-t border-border/70 bg-muted/35',
    'dark:bg-muted/20',
);

/** Meta badge cluster + CTA cluster layout inside the release footer. */
export const releaseFooterInnerClassName = cn(
    'flex flex-col gap-3 px-4 py-3',
    'sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5',
);

/** Primary download CTA on resource hero / confirm pages. */
export const downloadHeroButtonClassName = cn(
    'border border-transparent bg-primary px-4 text-primary-foreground shadow-none',
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
