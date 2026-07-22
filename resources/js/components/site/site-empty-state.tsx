import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    className?: string;
    children?: ReactNode;
};

export function SiteEmptyState({
    icon: Icon,
    title,
    description,
    className,
    children,
}: Props) {
    return (
        <div
            className={cn(
                'flex min-h-56 flex-col items-center justify-center gap-3 rounded-md border border-dashed border-border bg-card/50 px-6 py-12 text-center',
                className,
            )}
        >
            {Icon ? (
                <Icon
                    className="size-6 text-muted-foreground"
                    aria-hidden
                />
            ) : null}
            <div className="flex max-w-sm flex-col gap-1">
                <p className="text-sm font-medium text-foreground">{title}</p>
                {description ? (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </div>
    );
}
