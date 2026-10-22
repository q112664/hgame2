import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/**
 * A taxonomy dropdown in the navigation bar. Admins pick it in the menu manager
 * instead of the header hardcoding the Genres/Languages flyouts.
 */
export type NavigationMenuItemType = 'link' | 'taxonomy';

export type NavigationMenuTaxonomy =
    'categories' | 'platforms' | 'languages' | 'tags';

export type NavigationMenuItem = {
    type: NavigationMenuItemType;
    label: string;
    url: string;
    icon: string | null;
    openInNewTab: boolean;
    match: 'exact' | 'prefix' | 'none';
    /** Only set for `type: 'taxonomy'`. */
    taxonomy: NavigationMenuTaxonomy | null;
    /** Cap on how many taxonomy links to list; 0 means all. */
    limit: number;
};

/** Public footer link (admin-managed). */
export type FooterLinkItem = {
    label: string;
    url: string;
    openInNewTab: boolean;
};

/** Shared taxonomy directory for internal SEO links. */
export type TaxonomyNavLink = {
    name: string;
    value: string;
};

export type TaxonomyNav = {
    categories: TaxonomyNavLink[];
    platforms: TaxonomyNavLink[];
    languages: TaxonomyNavLink[];
    tags: TaxonomyNavLink[];
};
