import { SITE_LOCALE } from '@/lib/datetime';

const languageAbbreviations: Record<string, string> = {
    Chinese: 'CN',
    Japanese: 'JP',
    English: 'EN',
};

const categoryAbbreviations: Record<string, string> = {
    'Visual Novel': 'VN',
};

export function abbreviateLanguage(language: string): string {
    return (
        languageAbbreviations[language] ?? language.slice(0, 2).toUpperCase()
    );
}

export function abbreviateCategory(category: string): string {
    return categoryAbbreviations[category] ?? category;
}

export function abbreviateVersion(version: string): string {
    const withoutPrefix = version.trim().replace(/^(version|ver|v)\s*/i, '');
    const short = withoutPrefix.split(/\s+/)[0] ?? withoutPrefix;

    return short ? `v${short}` : version.trim();
}

export function formatViews(views: number): string {
    return new Intl.NumberFormat(SITE_LOCALE).format(views);
}

export function formatDate(date: string): string {
    return new Intl.DateTimeFormat(SITE_LOCALE, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

/**
 * Commercial release date for Western UI: e.g. Feb/02/2026 (MMM/DD/YYYY).
 */
export function formatReleaseDate(date: string): string {
    const parsed = /^\d{4}-\d{2}-\d{2}$/.test(date.trim())
        ? new Date(`${date.trim()}T00:00:00`)
        : new Date(date);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    const month = new Intl.DateTimeFormat(SITE_LOCALE, {
        month: 'short',
    }).format(parsed);
    const day = String(parsed.getDate()).padStart(2, '0');
    const year = String(parsed.getFullYear());

    return `${month}/${day}/${year}`;
}
