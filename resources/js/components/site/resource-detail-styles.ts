import { cn } from '@/lib/utils';

/** Shared flat badge shell — no border, soft fill, compact type. */
export const heroBadgeClassName = cn(
    'h-6 gap-1 rounded-md border-0 px-2.5 text-xs font-medium shadow-none',
    '[&>svg]:size-3.5!',
);

/** Category — neutral secondary surface */
export const categoryBadgeClassName = cn(
    heroBadgeClassName,
    'bg-secondary text-secondary-foreground',
);

/** Language — violet soft fill (distinct from platform blue / size green / rating amber) */
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
 */
export const downloadButtonClassName = cn(
    'h-8 min-w-0 gap-1.5 border border-transparent bg-primary px-3 text-sm font-medium text-primary-foreground shadow-none',
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
 * Shared idle shell for hero Rate / Favorite actions.
 * Same border + surface treatment in light and dark (no border-only-in-dark).
 */
export const heroActionIdleClassName = cn(
    'border border-border bg-muted text-muted-foreground shadow-none ring-0',
    'hover:bg-muted/80',
    'dark:border-foreground/15 dark:bg-surface-raised dark:text-foreground',
    'dark:hover:border-foreground/25 dark:hover:bg-surface-strong',
);

/** Active favorite state on the hero action shell. */
export const heroFavoriteActiveClassName = cn(
    'border border-favorite/30 bg-favorite/12 text-favorite shadow-none ring-0',
    'hover:border-favorite/40 hover:bg-favorite/18 hover:text-favorite',
    'dark:border-favorite/30 dark:bg-favorite/15 dark:text-favorite',
    'dark:hover:border-favorite/40 dark:hover:bg-favorite/25 dark:hover:text-favorite',
);

/** Active rating state on the hero action shell. */
export const heroRatingActiveClassName = cn(
    'border border-warning/30 bg-warning/12 text-warning shadow-none ring-0',
    'hover:border-warning/40 hover:bg-warning/18 hover:text-warning',
    'dark:border-warning/30 dark:bg-warning/15 dark:text-warning',
    'dark:hover:border-warning/40 dark:hover:bg-warning/25 dark:hover:text-warning',
);
