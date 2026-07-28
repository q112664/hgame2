import { Link, usePage } from '@inertiajs/react';
import { SiteLogo } from '@/components/site/site-logo';
import { Separator } from '@/components/ui/separator';
import { home } from '@/routes';

export function SiteFooter() {
    const { siteLogo } = usePage().props;
    const year = new Date().getFullYear();

    return (
        <footer className="mt-auto">
            <Separator />
            <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={home()}
                        className="transition-opacity hover:opacity-80"
                    >
                        <SiteLogo />
                    </Link>
                    <p className="text-xs text-muted-foreground">
                        Visual novel / galgame resource downloads
                    </p>
                </div>
                <p className="text-xs text-muted-foreground">
                    © {year} {siteLogo.text}. All rights reserved.
                </p>
            </div>
        </footer>
    );
}
