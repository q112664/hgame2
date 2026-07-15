import { cn } from '@/lib/utils';

export const heroBadgeClassName = cn(
    'h-6 gap-1 rounded-md border-transparent px-2.5 text-xs font-medium shadow-none',
    '[&>svg]:size-3.5!',
);

export const categoryBadgeClassName = cn(
    heroBadgeClassName,
    'bg-muted text-foreground',
);

export const languageBadgeClassName = cn(
    heroBadgeClassName,
    'bg-amber-500/12 text-amber-800 dark:text-amber-300',
);

const platformBadgeClassNames: Record<string, string> = {
    windows: cn(
        heroBadgeClassName,
        'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    ),
    ios: cn(
        heroBadgeClassName,
        'bg-rose-500/12 text-rose-700 dark:text-rose-300',
    ),
    android: cn(
        heroBadgeClassName,
        'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
    ),
};

export function platformBadgeClassName(slug: string): string {
    return (
        platformBadgeClassNames[slug.toLowerCase()] ??
        cn(
            heroBadgeClassName,
            'bg-indigo-500/12 text-indigo-700 dark:text-indigo-300',
        )
    );
}

export const metaChipClassName = cn(
    'inline-flex h-6 items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium',
);

export const fileSizeChipClassName = cn(
    metaChipClassName,
    'border-teal-500/25 bg-teal-500/12 text-teal-800 dark:text-teal-300',
);

export const dateChipClassName = cn(
    metaChipClassName,
    'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
);

export const downloadButtonPalettes = [
    'border-sky-500/25 bg-sky-500/10 text-sky-800 hover:bg-sky-500/15 dark:text-sky-200',
    'border-violet-500/25 bg-violet-500/10 text-violet-800 hover:bg-violet-500/15 dark:text-violet-200',
    'border-emerald-500/25 bg-emerald-500/10 text-emerald-800 hover:bg-emerald-500/15 dark:text-emerald-200',
    'border-amber-500/25 bg-amber-500/10 text-amber-900 hover:bg-amber-500/15 dark:text-amber-200',
    'border-rose-500/25 bg-rose-500/10 text-rose-800 hover:bg-rose-500/15 dark:text-rose-200',
] as const;
