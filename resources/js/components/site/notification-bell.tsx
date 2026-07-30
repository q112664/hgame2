import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const iconButtonClassName = cn(
    'relative inline-flex size-8 items-center justify-center rounded-md text-foreground',
    'transition-colors outline-none select-none',
    'hover:bg-foreground/10',
    'focus-visible:ring-2 focus-visible:ring-ring/50',
);

export function NotificationBell() {
    const page = usePage();
    const unreadCount = page.props.notificationSummary?.unreadCount ?? 0;
    const badgeLabel =
        unreadCount > 99 ? '99+' : unreadCount > 0 ? String(unreadCount) : null;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href="/notifications"
                    className={iconButtonClassName}
                    aria-label={
                        unreadCount > 0
                            ? `Notifications, ${unreadCount} unread`
                            : 'Notifications'
                    }
                    prefetch
                >
                    <Bell className="size-4 shrink-0" strokeWidth={1.75} />
                    {badgeLabel ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-semibold text-primary-foreground shadow-sm">
                            {badgeLabel}
                        </span>
                    ) : null}
                </Link>
            </TooltipTrigger>
            <TooltipContent side="bottom" sideOffset={4}>
                Notifications
            </TooltipContent>
        </Tooltip>
    );
}
