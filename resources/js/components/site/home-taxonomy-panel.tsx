import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    genre as resourcesGenre,
    language as resourcesLanguage,
    platform as resourcesPlatform,
} from '@/routes/resources';
import type { TaxonomyNav, TaxonomyNavLink } from '@/types/navigation';

type Props = {
    nav: TaxonomyNav;
    className?: string;
};

const chipClassName = cn(
    'inline-flex h-7 max-w-full items-center rounded-md px-2',
    'text-xs font-medium text-muted-foreground no-underline',
    'bg-muted/70 transition-colors',
    'hover:bg-primary/10 hover:text-primary',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

function Row({
    title,
    items,
    hrefFor,
    limit,
}: {
    title: string;
    items: TaxonomyNavLink[];
    hrefFor: (item: TaxonomyNavLink) => string;
    limit: number;
}) {
    if (items.length === 0) {
        return null;
    }

    const shown = items.slice(0, limit);

    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            <h3 className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            <div className="flex flex-wrap gap-1">
                {shown.map((item) => (
                    <Link
                        key={`${title}-${item.value}`}
                        href={hrefFor(item)}
                        className={chipClassName}
                        prefetch
                    >
                        <span className="truncate">{item.name}</span>
                    </Link>
                ))}
            </div>
        </div>
    );
}

/**
 * Compact genre / platform / language links for the homepage hero row.
 */
export function HomeTaxonomyPanel({ nav, className }: Props) {
    const hasAny =
        nav.categories.length > 0 ||
        nav.platforms.length > 0 ||
        nav.languages.length > 0;

    if (!hasAny) {
        return null;
    }

    return (
        <aside
            aria-labelledby="home-taxonomy-heading"
            className={cn(
                'flex h-full min-h-0 flex-col gap-3 rounded-md border border-border/80 bg-card p-3 sm:p-3.5',
                className,
            )}
        >
            <h2
                id="home-taxonomy-heading"
                className="font-heading text-sm font-semibold tracking-tight text-foreground"
            >
                Browse
            </h2>

            <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto">
                <Row
                    title="Genres"
                    items={nav.categories}
                    hrefFor={(item) => resourcesGenre.url(item.value)}
                    limit={8}
                />
                <Row
                    title="Platforms"
                    items={nav.platforms}
                    hrefFor={(item) => resourcesPlatform.url(item.value)}
                    limit={6}
                />
                <Row
                    title="Languages"
                    items={nav.languages}
                    hrefFor={(item) => resourcesLanguage.url(item.value)}
                    limit={6}
                />
            </div>
        </aside>
    );
}
