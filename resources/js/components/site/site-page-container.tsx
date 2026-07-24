import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type SitePageContainerProps = ComponentProps<'div'> & {
    /** Tighter vertical padding for dense pages such as resource detail. */
    density?: 'default' | 'compact';
};

export function SitePageContainer({
    className,
    density = 'default',
    ...props
}: SitePageContainerProps) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-7xl flex-col px-4 sm:px-6 lg:px-8',
                density === 'compact'
                    ? 'gap-3 pt-4 pb-8 sm:gap-4 sm:pt-5 sm:pb-10'
                    : 'py-10 sm:py-12',
                className,
            )}
            {...props}
        />
    );
}
