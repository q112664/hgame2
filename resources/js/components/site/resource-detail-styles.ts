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

/** Language — warm soft fill */
export const languageBadgeClassName = cn(
    heroBadgeClassName,
    'bg-warning/15 text-warning',
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
 * Compact download chip for release link rows — quiet surface, primary hover.
 * Dark mode keeps a raised surface and uses a soft primary tint on hover.
 */
export const downloadButtonClassName = cn(
    'h-7 gap-1 border border-border/80 bg-muted/60 px-2.5 text-xs text-foreground shadow-none',
    'hover:border-primary/30 hover:bg-primary hover:text-primary-foreground',
    'dark:border-foreground/15 dark:bg-surface-raised dark:text-foreground',
    'dark:hover:border-primary/35 dark:hover:bg-primary/18 dark:hover:text-primary',
);

/** Primary download CTA on resource hero / confirm pages. */
export const downloadHeroButtonClassName = cn(
    'border border-transparent bg-primary px-4 text-primary-foreground shadow-none',
    'hover:bg-primary/90',
    'dark:border-primary/40 dark:bg-primary/90',
    'dark:hover:border-primary/50 dark:hover:bg-primary',
);

