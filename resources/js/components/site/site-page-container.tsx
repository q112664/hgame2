import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function SitePageContainer({
    className,
    ...props
}: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-7xl flex-col px-4 py-10 sm:px-6 sm:py-12 lg:px-8',
                className,
            )}
            {...props}
        />
    );
}
