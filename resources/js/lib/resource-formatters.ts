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

/**
 * Site-wide calendar date: e.g. Jul/04/2026 (MMM/DD/YYYY).
 * Accepts YYYY-MM-DD or any Date-parseable string.
 */
export function formatDate(date: string): string {
    const trimmed = date.trim();
    const parsed = /^\d{4}-\d{2}-\d{2}$/.test(trimmed)
        ? new Date(`${trimmed}T00:00:00`)
        : new Date(trimmed);

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

/** Alias of formatDate for commercial release dates. */
export function formatReleaseDate(date: string): string {
    return formatDate(date);
}
