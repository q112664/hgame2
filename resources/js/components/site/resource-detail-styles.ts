import { cn } from '@/lib/utils';

export const heroBadgeClassName = cn(
    'h-6 gap-1 rounded-sm border-transparent px-2.5 text-xs font-medium shadow-none',
    '[&>svg]:size-3.5!',
);

export const categoryBadgeClassName = cn(
    heroBadgeClassName,
    'bg-muted text-foreground',
);

export const languageBadgeClassName = cn(
    heroBadgeClassName,
    'bg-warning/12 text-warning',
);

const platformBadgeClassNames: Record<string, string> = {
    windows: cn(heroBadgeClassName, 'bg-info/12 text-info'),
    ios: cn(heroBadgeClassName, 'bg-primary/12 text-primary'),
    android: cn(heroBadgeClassName, 'bg-success/12 text-success'),
};

export function platformBadgeClassName(slug: string): string {
    return (
        platformBadgeClassNames[slug.toLowerCase()] ??
        cn(heroBadgeClassName, 'bg-accent text-accent-foreground')
    );
}

export const metaChipClassName = cn(
    'inline-flex h-6 items-center gap-1.5 rounded-sm border px-2.5 text-xs font-medium',
);

export const fileSizeChipClassName = cn(
    metaChipClassName,
    'border-success/25 bg-success/12 text-success',
);

export const dateChipClassName = cn(
    metaChipClassName,
    'border-border bg-muted text-muted-foreground',
);

export const downloadButtonPalettes = [
    'border-primary/30 bg-primary/10 text-primary hover:bg-primary/15',
    'border-info/30 bg-info/10 text-info hover:bg-info/15',
    'border-success/30 bg-success/10 text-success hover:bg-success/15',
] as const;
