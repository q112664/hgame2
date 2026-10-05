import type { AppNotificationItem } from '@/types/notifications';

export type NotificationDisplay = {
    title: string;
    detail: string | null;
};

/** One title and at most one short line. Update notices never repeat the game name. */
export function notificationDisplay(
    notification: AppNotificationItem,
): NotificationDisplay {
    if (
        notification.type === 'favorite.downloads_updated' ||
        notification.type.startsWith('favorite.')
    ) {
        const gameTitle = notification.data.game_title;

        return {
            title:
                typeof gameTitle === 'string' && gameTitle !== ''
                    ? gameTitle
                    : notification.title,
            detail: 'Downloads updated',
        };
    }

    const body =
        typeof notification.body === 'string' && notification.body !== ''
            ? notification.body
            : null;

    return {
        title: notification.title,
        detail: body === notification.title ? null : body,
    };
}
