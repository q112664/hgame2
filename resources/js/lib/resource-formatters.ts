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
    return new Intl.NumberFormat('en-US').format(views);
}

export function formatDate(date: string): string {
    const [year, month, day] = date.split('-');

    return `${year}-${month}-${day}`;
}
