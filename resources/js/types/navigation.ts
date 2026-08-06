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

export type NavigationMenuItem = {
    label: string;
    url: string;
    icon: string | null;
    openInNewTab: boolean;
    match: 'exact' | 'prefix' | 'none';
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
