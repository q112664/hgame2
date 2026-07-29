export type NotificationActor = {
    id: number;
    name: string;
    avatar: string | null;
};

export type AppNotificationItem = {
    id: string;
    type: string;
    title: string;
    body: string | null;
    url: string | null;
    actor: NotificationActor | null;
    readAt: string | null;
    createdAt: string | null;
    data: Record<string, unknown>;
};

export type NotificationsShared = {
    unreadCount: number;
};
