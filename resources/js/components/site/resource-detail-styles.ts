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

/** Clickable tags in the details tab — larger, strong primary hover */
export const tagBadgeClassName = cn(
    'inline-flex h-7 w-fit shrink-0 items-center justify-center',
    'rounded-md px-3 text-sm font-medium no-underline',
    'bg-muted text-muted-foreground',
    'transition-[color,background-color] duration-150',
    'hover:bg-primary hover:text-primary-foreground',
    'active:bg-primary/90 active:text-primary-foreground',
    'focus-visible:bg-primary focus-visible:text-primary-foreground',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

const platformBadgeClassNames: Record<string, string> = {
    windows: cn(heroBadgeClassName, 'bg-info/15 text-info'),
    ios: cn(heroBadgeClassName, 'bg-primary/12 text-primary'),
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

/** Shared download CTA — neutral so color is not mistaken for host meaning. */
export const downloadButtonClassName = cn(
    'h-8 border-0 bg-primary/12 text-primary shadow-none',
    'hover:bg-primary/18 dark:bg-primary/15 dark:hover:bg-primary/25',
);

