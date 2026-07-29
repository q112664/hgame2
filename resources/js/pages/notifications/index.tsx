import { Head, router } from '@inertiajs/react';
import {
    Bell,
    CheckCheck,
    Download,
    Megaphone,
    MessageSquare,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { RouteTabs } from '@/components/site/route-tabs';
import type { RouteTab } from '@/components/site/route-tabs';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePageContainer } from '@/components/site/site-page-container';
import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { SiteLayout } from '@/layouts/site-layout';
import { formatAbsoluteDateTime, formatRelativeTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import type { AppNotificationItem } from '@/types/notifications';

type NotificationTabValue = 'all' | 'comments' | 'favorites' | 'system';

type NotificationTabItem = RouteTab<NotificationTabValue> & {
    count: number;
    unreadCount: number;
};

type Props = {
    activeTab: NotificationTabValue;
    tabs: NotificationTabItem[];
    notifications: PaginatedData<AppNotificationItem>;
};

function NotificationTypeIcon({ type }: { type: string }) {
    if (type === 'comment.replied' || type.startsWith('comment.')) {
        return (
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-primary/15">
                <MessageSquare className="size-4" aria-hidden />
            </span>
        );
    }

    if (type === 'favorite.downloads_updated' || type.startsWith('favorite.')) {
        return (
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-info/12 text-info ring-1 ring-info/20">
                <Download className="size-4" aria-hidden />
            </span>
        );
    }

    if (type === 'system.broadcast' || type.startsWith('system.')) {
        return (
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning ring-1 ring-warning/25">
                <Megaphone className="size-4" aria-hidden />
            </span>
        );
    }

    return (
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground ring-1 ring-border/60">
            <Bell className="size-4" aria-hidden />
        </span>
    );
}

function tabHref(tab: NotificationTabValue, page?: number): string {
    const base = tab === 'all' ? '/notifications' : `/notifications/${tab}`;

    if (page && page > 1) {
        return `${base}?page=${page}`;
    }

    return base;
}

export default function NotificationsIndex({
    activeTab,
    tabs,
    notifications,
}: Props) {
    const [openingId, setOpeningId] = useState<string | null>(null);
    const [markingAll, setMarkingAll] = useState(false);
    const [clearing, setClearing] = useState(false);

    const activeMeta = tabs.find((tab) => tab.value === activeTab);
    const unreadInTab = activeMeta?.unreadCount ?? 0;
    const countInTab = activeMeta?.count ?? 0;

    const routeTabs: RouteTab<NotificationTabValue>[] = tabs.map((tab) => ({
        value: tab.value,
        label:
            tab.unreadCount > 0
                ? `${tab.label} (${tab.unreadCount})`
                : tab.label,
        href: tab.href,
    }));

    const openNotification = (notification: AppNotificationItem) => {
        if (openingId !== null) {
            return;
        }

        setOpeningId(notification.id);

        router.post(
            `/notifications/${notification.id}/read`,
            { open: notification.url ? 1 : 0 },
            {
                preserveScroll: !notification.url,
                onFinish: () => setOpeningId(null),
            },
        );
    };

    const markAllAsRead = () => {
        if (unreadInTab === 0 || markingAll || clearing) {
            return;
        }

        setMarkingAll(true);
        router.post(
            '/notifications/read-all',
            { tab: activeTab },
            {
                preserveScroll: true,
                only: ['notifications', 'tabs'],
                onFinish: () => setMarkingAll(false),
            },
        );
    };

    const clearAll = () => {
        if (countInTab === 0 || clearing || markingAll) {
            return;
        }

        if (
            !window.confirm(
                activeTab === 'all'
                    ? 'Clear all notifications? This cannot be undone.'
                    : 'Clear all notifications in this tab? This cannot be undone.',
            )
        ) {
            return;
        }

        setClearing(true);
        router.post(
            '/notifications/clear',
            { tab: activeTab },
            {
                preserveScroll: true,
                only: ['notifications', 'tabs'],
                onFinish: () => setClearing(false),
            },
        );
    };

    return (
        <SiteLayout>
            <Head title="Notifications" />

            <SitePageContainer className="gap-6 sm:gap-8">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Notifications
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Replies and other updates across the site.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={
                                unreadInTab === 0 || markingAll || clearing
                            }
                            onClick={markAllAsRead}
                        >
                            <CheckCheck className="size-3.5" />
                            {markingAll ? 'Marking…' : 'Mark all as read'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="text-muted-foreground hover:border-destructive/40 hover:bg-destructive/10 hover:text-destructive"
                            disabled={
                                countInTab === 0 || clearing || markingAll
                            }
                            onClick={clearAll}
                        >
                            <Trash2 className="size-3.5" />
                            {clearing ? 'Clearing…' : 'Clear all'}
                        </Button>
                    </div>
                </div>

                <RouteTabs
                    tabs={routeTabs}
                    activeValue={activeTab}
                    ariaLabel="Notification types"
                />

                <div id="notification-results" className="scroll-mt-20">
                    {notifications.data.length > 0 ? (
                        <ul className="divide-y divide-border/70 overflow-hidden rounded-lg border border-border bg-card">
                            {notifications.data.map((notification) => {
                                const unread = !notification.readAt;
                                const isOpening = openingId === notification.id;

                                const handleRowActivate = () => {
                                    if (isOpening) {
                                        return;
                                    }

                                    // Allow drag-select / copy without navigating.
                                    if (
                                        window.getSelection()?.toString().trim()
                                    ) {
                                        return;
                                    }

                                    openNotification(notification);
                                };

                                return (
                                    <li
                                        key={notification.id}
                                        className={cn(
                                            'transition-colors select-text',
                                            'hover:bg-muted/50',
                                            isOpening && 'opacity-70',
                                            unread && 'bg-primary/4',
                                        )}
                                    >
                                        <div
                                            role="button"
                                            tabIndex={isOpening ? -1 : 0}
                                            aria-disabled={
                                                isOpening || undefined
                                            }
                                            aria-label={
                                                notification.url
                                                    ? `Open notification: ${notification.title}`
                                                    : `Mark notification as read: ${notification.title}`
                                            }
                                            className={cn(
                                                'flex w-full cursor-pointer gap-3 px-4 py-3.5 text-left sm:gap-3.5 sm:px-5 sm:py-4',
                                                'focus-visible:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none focus-visible:ring-inset',
                                            )}
                                            onClick={handleRowActivate}
                                            onKeyDown={(event) => {
                                                if (
                                                    event.key === 'Enter' ||
                                                    event.key === ' '
                                                ) {
                                                    event.preventDefault();
                                                    handleRowActivate();
                                                }
                                            }}
                                        >
                                            {notification.actor ? (
                                                <UserAvatar
                                                    user={notification.actor}
                                                    className="mt-0.5 size-10 shrink-0 ring-1 ring-border/60"
                                                    fallbackClassName="rounded-full bg-muted text-xs text-muted-foreground"
                                                />
                                            ) : (
                                                <NotificationTypeIcon
                                                    type={notification.type}
                                                />
                                            )}

                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start justify-between gap-3">
                                                    <p
                                                        className={cn(
                                                            'text-sm leading-snug text-foreground sm:text-[15px]',
                                                            unread &&
                                                                'font-medium',
                                                        )}
                                                    >
                                                        {notification.title}
                                                    </p>
                                                    {unread ? (
                                                        <span
                                                            className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                                            aria-label="Unread"
                                                        />
                                                    ) : null}
                                                </div>

                                                {notification.body ? (
                                                    <p className="mt-1 text-sm leading-relaxed break-words text-muted-foreground">
                                                        {notification.body}
                                                    </p>
                                                ) : null}

                                                <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                                                    {typeof notification.data
                                                        .game_title ===
                                                    'string' ? (
                                                        <span className="truncate font-medium text-foreground/70">
                                                            {
                                                                notification
                                                                    .data
                                                                    .game_title as string
                                                            }
                                                        </span>
                                                    ) : null}
                                                    {notification.createdAt ? (
                                                        <time
                                                            dateTime={
                                                                notification.createdAt
                                                            }
                                                            title={formatAbsoluteDateTime(
                                                                notification.createdAt,
                                                            )}
                                                            className="tabular-nums"
                                                        >
                                                            {formatRelativeTime(
                                                                notification.createdAt,
                                                            )}
                                                        </time>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <SiteEmptyState
                            icon={
                                activeTab === 'favorites'
                                    ? Download
                                    : activeTab === 'comments'
                                      ? MessageSquare
                                      : Bell
                            }
                            title={
                                notifications.total > 0
                                    ? 'No notifications on this page'
                                    : activeTab === 'comments'
                                      ? 'No comment notifications'
                                      : activeTab === 'favorites'
                                        ? 'No favorite updates'
                                        : 'No notifications yet'
                            }
                            description={
                                notifications.total > 0
                                    ? 'Try another page.'
                                    : activeTab === 'comments'
                                      ? 'When someone replies to your comments, it will show up here.'
                                      : activeTab === 'favorites'
                                        ? 'When a favorited resource gets new downloads, it will show up here.'
                                        : 'Replies, favorite updates, and other site activity will appear here.'
                            }
                        />
                    )}

                    <div className="mt-8">
                        <SitePagination
                            pagination={notifications}
                            pageUrl={(page) => tabHref(activeTab, page)}
                            ariaLabel="Notifications pagination"
                            itemLabel="notifications"
                            only={['notifications', 'tabs', 'activeTab']}
                            onSuccess={() => {
                                document
                                    .getElementById('notification-results')
                                    ?.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start',
                                    });
                            }}
                        />
                    </div>
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
