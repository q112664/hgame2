import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    genre as resourcesGenre,
    language as resourcesLanguage,
    platform as resourcesPlatform,
    tag as resourcesTag,
    tags as resourcesTagsIndex,
} from '@/routes/resources';
import type { TaxonomyNav, TaxonomyNavLink } from '@/types/navigation';

type Props = {
    nav: TaxonomyNav;
    /** Compact = fewer groups / denser chips (footer, home). */
    density?: 'default' | 'compact';
    className?: string;
    /** Optional section heading level for the block title. */
    heading?: string;
    headingId?: string;
};

const chipClassName = cn(
    'inline-flex h-7 max-w-full items-center rounded-md px-2.5',
    'text-xs font-medium text-muted-foreground no-underline',
    'bg-muted/70 transition-colors',
    'hover:bg-primary/10 hover:text-primary',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

function Group({
    title,
    items,
    hrefFor,
}: {
    title: string;
    items: TaxonomyNavLink[];
    hrefFor: (item: TaxonomyNavLink) => string;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            <div className="flex flex-wrap gap-1.5">
                {items.map((item) => (
                    <ChipLink key={`${title}-${item.value}`} href={hrefFor(item)}>
                        {item.name}
                    </ChipLink>
                ))}
            </div>
        </div>
    );
}

function ChipLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Link href={href} className={chipClassName} prefetch>
            <span className="truncate">{children}</span>
        </Link>
    );
}

/**
 * Internal links into indexable taxonomy landings (genre / platform / language / tag).
 */
export function CatalogBrowseLinks({
    nav,
    density = 'default',
    className,
    heading = 'Browse catalog',
    headingId = 'catalog-browse-heading',
}: Props) {
    const categories = nav.categories;
    const platforms = nav.platforms;
    const languages = nav.languages;
    const tags = density === 'compact' ? nav.tags.slice(0, 16) : nav.tags;

    const hasAny =
        categories.length > 0 ||
        platforms.length > 0 ||
        languages.length > 0 ||
        tags.length > 0;

    if (!hasAny) {
        return null;
    }

    return (
        <section
            aria-labelledby={headingId}
            className={cn(
                'rounded-md border border-border/80 bg-card',
                density === 'compact' ? 'p-3 sm:p-4' : 'p-4 sm:p-5',
                className,
            )}
        >
            <h2
                id={headingId}
                className={cn(
                    'font-heading font-semibold tracking-tight text-foreground',
                    density === 'compact'
                        ? 'mb-3 text-sm'
                        : 'mb-4 text-base sm:text-lg',
                )}
            >
                {heading}
            </h2>

            <div
                className={cn(
                    'flex flex-col',
                    density === 'compact' ? 'gap-3' : 'gap-4',
                )}
            >
                <Group
                    title="Genres"
                    items={categories}
                    hrefFor={(item) => resourcesGenre.url(item.value)}
                />
                <Group
                    title="Platforms"
                    items={platforms}
                    hrefFor={(item) => resourcesPlatform.url(item.value)}
                />
                <Group
                    title="Languages"
                    items={
                        density === 'compact'
                            ? languages.slice(0, 8)
                            : languages
                    }
                    hrefFor={(item) => resourcesLanguage.url(item.value)}
                />
                <Group
                    title="Tags"
                    items={tags}
                    hrefFor={(item) => resourcesTag.url(item.value)}
                />
                {nav.tags.length > 0 ? (
                    <div className="pt-0.5">
                        <Link
                            href={resourcesTagsIndex.url()}
                            className="text-xs font-medium text-primary hover:underline"
                            prefetch
                        >
                            View all tags
                        </Link>
                    </div>
                ) : null}
            </div>
        </section>
    );
}
