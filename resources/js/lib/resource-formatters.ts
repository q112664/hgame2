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
 * Parses the date shapes the API sends: YYYY-MM-DD is read as a calendar day in
 * the site timezone, anything else (ISO timestamps) goes through `Date`.
 */
function parseCalendarDate(date: string): Date {
    const trimmed = date.trim();

    return /^\d{4}-\d{2}-\d{2}$/.test(trimmed)
        ? new Date(`${trimmed}T00:00:00`)
        : new Date(trimmed);
}

/**
 * Site-wide calendar date in en-US medium style: e.g. Jul 4, 2026.
 * Accepts YYYY-MM-DD or any Date-parseable string.
 */
export function formatDate(date: string): string {
    const parsed = parseCalendarDate(date);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return new Intl.DateTimeFormat(SITE_LOCALE, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(parsed);
}

/** Alias of formatDate for commercial release dates. */
export function formatReleaseDate(date: string): string {
    return formatDate(date);
}

/**
 * Numeric calendar date, e.g. 9/8/2026, with the full year. Used in thumbnail
 * chips where a written month would crowd the artwork.
 */
export function formatCompactDate(date: string): string {
    const parsed = parseCalendarDate(date);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return new Intl.DateTimeFormat(SITE_LOCALE, {
        month: 'numeric',
        day: 'numeric',
        year: 'numeric',
    }).format(parsed);
}
