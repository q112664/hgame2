/** Site UI targets Western users — always format dates in English. */
export const SITE_LOCALE = 'en-US';

export function formatRelativeTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const abs = Math.abs(diffSeconds);
    const rtf = new Intl.RelativeTimeFormat(SITE_LOCALE, { numeric: 'auto' });

    if (abs < 60) {
        return rtf.format(Math.round(diffSeconds), 'second');
    }

    if (abs < 3600) {
        return rtf.format(Math.round(diffSeconds / 60), 'minute');
    }

    if (abs < 86400) {
        return rtf.format(Math.round(diffSeconds / 3600), 'hour');
    }

    if (abs < 86400 * 30) {
        return rtf.format(Math.round(diffSeconds / 86400), 'day');
    }

    if (abs < 86400 * 365) {
        return rtf.format(Math.round(diffSeconds / (86400 * 30)), 'month');
    }

    return rtf.format(Math.round(diffSeconds / (86400 * 365)), 'year');
}

export function formatAbsoluteDateTime(
    value: string | null | undefined,
): string {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(SITE_LOCALE, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}
