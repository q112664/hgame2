import { router } from '@inertiajs/react';
import {
    Bell,
    Check,
    CheckCheck,
    ChevronRight,
    Download,
    Megaphone,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
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
import { notificationDisplay } from '@/lib/notification-display';
import { cn } from '@/lib/utils';
import type { AppNotificationItem } from '@/types/notifications';

type NotificationTabValue = 'all' | 'favorites' | 'system';

type NotificationTabItem = RouteTab<NotificationTabValue> & {
    count: number;
    unreadCount: number;
};

type Props = {
    activeTab: NotificationTabValue;
    tabs: NotificationTabItem[];
    notifications: PaginatedData<AppNotificationItem>;
    pageSeo?: PageSeoData | null;
};

function NotificationTypeIcon({ type }: { type: string }) {
    if (type === 'favorite.downloads_updated' || type.startsWith('favorite.')) {
        return (
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                <Download className="size-4" aria-hidden />
            </span>
        );
    }

    if (type === 'system.broadcast' || type.startsWith('system.')) {
        return (
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                <Megaphone className="size-4" aria-hidden />
            </span>
        );
    }

    return (
        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
            <Bell className="size-4" aria-hidden />
        </span>
    );
}

function tabDescription(tab: NotificationTabValue): string {
    if (tab === 'favorites') {
        return 'New downloads on games you favorited.';
    }

    if (tab === 'system') {
        return 'Announcements from the site.';
    }

    return 'Download updates and announcements.';
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
    pageSeo,
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

    const postRead = (notification: AppNotificationItem, open: boolean) => {
        if (openingId !== null) {
            return;
        }

        const shouldOpen = open && Boolean(notification.url);

        setOpeningId(notification.id);

        router.post(
            `/notifications/${notification.id}/read`,
            { open: shouldOpen ? 1 : 0 },
            {
                preserveScroll: !shouldOpen,
                ...(shouldOpen
                    ? {}
                    : {
                          only: [
                              'notifications',
                              'tabs',
                              'notificationSummary',
                          ],
                      }),
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
                only: ['notifications', 'tabs', 'notificationSummary'],
                onFinish: () => setMarkingAll(false),
            },
        );
    };

    const clearAll = () => {
        if (countInTab === 0 || clearing || markingAll) {
            return;
        }

        const clearLabel =
            activeTab === 'favorites'
                ? 'favorite updates'
                : activeTab === 'system'
                  ? 'announcements'
                  : 'notifications';

        if (!window.confirm(`Clear ${clearLabel}? This cannot be undone.`)) {
            return;
        }

        setClearing(true);
        router.post(
            '/notifications/clear',
            { tab: activeTab },
            {
                preserveScroll: true,
                only: ['notifications', 'tabs', 'notificationSummary'],
                onFinish: () => setClearing(false),
            },
        );
    };

    return (
        <SiteLayout>
            <PageSeo seo={pageSeo} title="Notifications" />

            <SitePageContainer className="gap-6 sm:gap-8">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Notifications
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {tabDescription(activeTab)}
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
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
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
                                const display =
                                    notificationDisplay(notification);

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

                                    postRead(notification, true);
                                };

                                return (
                                    <li
                                        key={notification.id}
                                        className={cn(
                                            'relative flex transition-colors select-text',
                                            'hover:bg-muted/50',
                                            isOpening && 'opacity-70',
                                            unread && 'bg-primary/4',
                                        )}
                                    >
                                        {unread ? (
                                            <span
                                                className="absolute inset-y-0 left-0 w-0.5 bg-primary"
                                                aria-hidden
                                            />
                                        ) : null}
                                        <div
                                            role="button"
                                            tabIndex={isOpening ? -1 : 0}
                                            aria-disabled={
                                                isOpening || undefined
                                            }
                                            aria-label={
                                                notification.url
                                                    ? `Open ${display.title}`
                                                    : `Mark as read: ${display.title}`
                                            }
                                            className={cn(
                                                'flex min-w-0 flex-1 cursor-pointer items-center gap-3 px-4 py-3 text-left sm:px-5',
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
                                                    className="size-8 shrink-0 ring-1 ring-border/60"
                                                    fallbackClassName="rounded-full bg-muted text-xs text-muted-foreground"
                                                />
                                            ) : (
                                                <NotificationTypeIcon
                                                    type={notification.type}
                                                />
                                            )}

                                            <div className="min-w-0 flex-1">
                                                <p
                                                    className={cn(
                                                        'truncate text-sm text-foreground',
                                                        unread && 'font-medium',
                                                    )}
                                                >
                                                    {display.title}
                                                </p>
                                                {display.detail ? (
                                                    <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                                        {display.detail}
                                                    </p>
                                                ) : null}
                                            </div>
                                            {notification.createdAt ? (
                                                <time
                                                    dateTime={
                                                        notification.createdAt
                                                    }
                                                    title={formatAbsoluteDateTime(
                                                        notification.createdAt,
                                                    )}
                                                    className="shrink-0 self-center text-right text-xs leading-4 text-muted-foreground tabular-nums"
                                                >
                                                    {formatRelativeTime(
                                                        notification.createdAt,
                                                    )}
                                                </time>
                                            ) : null}
                                            <span
                                                className="inline-flex size-4 shrink-0 items-center justify-center text-muted-foreground"
                                                aria-hidden
                                            >
                                                {notification.url ? (
                                                    <ChevronRight className="size-4" />
                                                ) : null}
                                            </span>
                                        </div>
                                        {unread ? (
                                            <button
                                                type="button"
                                                className="mr-2 inline-flex size-8 shrink-0 items-center justify-center self-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none sm:mr-3"
                                                aria-label={`Mark as read: ${display.title}`}
                                                disabled={isOpening}
                                                onClick={() =>
                                                    postRead(
                                                        notification,
                                                        false,
                                                    )
                                                }
                                            >
                                                <Check
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </button>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <SiteEmptyState
                            icon={activeTab === 'favorites' ? Download : Bell}
                            title={
                                notifications.total > 0
                                    ? 'No notifications on this page'
                                    : activeTab === 'favorites'
                                      ? 'No favorite updates'
                                      : activeTab === 'system'
                                        ? 'No announcements'
                                        : 'No notifications yet'
                            }
                            description={
                                notifications.total > 0
                                    ? 'Try another page.'
                                    : tabDescription(activeTab)
                            }
                        />
                    )}

                    <div className="mt-8">
                        <SitePagination
                            pagination={notifications}
                            pageUrl={(page) => tabHref(activeTab, page)}
                            ariaLabel="Notifications pagination"
                            itemLabel="notifications"
                            only={[
                                'notifications',
                                'tabs',
                                'activeTab',
                                'notificationSummary',
                            ]}
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
