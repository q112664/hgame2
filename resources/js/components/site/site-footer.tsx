import { Link, usePage } from '@inertiajs/react';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import type { FooterLinkItem } from '@/types/navigation';

function isExternalUrl(url: string): boolean {
    return url.startsWith('http://') || url.startsWith('https://');
}

const footerLinkClassName = cn(
    'text-xs text-muted-foreground transition-colors',
    'hover:text-foreground',
    'focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

function FooterNavLink({ item }: { item: FooterLinkItem }) {
    if (isExternalUrl(item.url) || item.openInNewTab) {
        return (
            <a
                href={item.url}
                className={footerLinkClassName}
                {...(item.openInNewTab || isExternalUrl(item.url)
                    ? {
                          target: '_blank',
                          rel: 'noopener noreferrer',
                      }
                    : {})}
            >
                {item.label}
            </a>
        );
    }

    return (
        <Link href={item.url} className={footerLinkClassName} prefetch>
            {item.label}
        </Link>
    );
}

export function SiteFooter() {
    const { siteLogo, footerLinks = [] } = usePage().props;
    const year = new Date().getFullYear();
    const hasLinks = footerLinks.length > 0;

    return (
        <footer className="mt-auto">
            <Separator />
            <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-10 sm:px-6 lg:px-8">
                <div
                    className={cn(
                        'flex flex-col gap-3 sm:flex-row sm:items-center',
                        hasLinks ? 'sm:justify-between' : 'sm:justify-start',
                    )}
                >
                    <p className="text-xs text-muted-foreground">
                        © {year} {siteLogo.text}. All rights reserved.
                    </p>

                    {hasLinks ? (
                        <nav
                            aria-label="Footer"
                            className="flex flex-wrap items-center gap-x-4 gap-y-2 sm:justify-end"
                        >
                            {footerLinks.map((item) => (
                                <FooterNavLink
                                    key={`${item.label}-${item.url}`}
                                    item={item}
                                />
                            ))}
                        </nav>
                    ) : null}
                </div>
            </div>
        </footer>
    );
}
