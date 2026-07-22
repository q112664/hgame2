import { Link } from '@inertiajs/react';
import { SiteLogo } from '@/components/site/site-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col bg-surface-inverse p-10 text-surface-inverse-foreground lg:flex dark:border-r">
                <div className="absolute inset-0 bg-surface-inverse" />
                <Link
                    href={home()}
                    className="relative z-20 flex items-center transition-opacity hover:opacity-80"
                >
                    <SiteLogo
                        textClassName="text-lg text-surface-inverse-foreground"
                        imageClassName="h-8 brightness-0 invert"
                    />
                </Link>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center transition-opacity hover:opacity-80 lg:hidden"
                    >
                        <SiteLogo imageClassName="h-10 sm:h-12" />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
