import { usePage } from '@inertiajs/react';
import { Separator } from '@/components/ui/separator';

export function SiteFooter() {
    const { siteLogo } = usePage().props;
    const year = new Date().getFullYear();

    return (
        <footer className="mt-auto">
            <Separator />
            <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-10 sm:px-6 lg:px-8">
                <p className="text-xs text-muted-foreground">
                    © {year} {siteLogo.text}. All rights reserved.
                </p>
            </div>
        </footer>
    );
}
