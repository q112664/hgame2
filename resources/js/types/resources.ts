export type GamePlatform = {
    name: string;
    slug: string;
};

export type GameLanguage = {
    name: string;
    code: string;
};

export type GameTag = {
    name: string;
    slug: string;
};

export type GameSource = {
    name: string | null;
    id: string | null;
    url: string | null;
    faviconUrl: string | null;
};

export type GameCard = {
    id: string;
    title: string;
    subtitle: string | null;
    thumbnail: string;
    category: string;
    /** Null when uncategorized (not linked). */
    categorySlug: string | null;
    developer: string;
    source: GameSource | null;
    platforms: GamePlatform[];
    languages: GameLanguage[];
    version: string | null;
    tags: GameTag[];
    releaseDate: string | null;
    publishedAt: string | null;
    views: number;
    hasDownloadUpdate?: boolean;
};

export type GameUpdateListItem = {
    id: string;
    title: string;
    subtitle: string | null;
    thumbnail: string;
    developer: string;
    version: string | null;
    platforms: GamePlatform[];
    languages: GameLanguage[];
    updatedAt: string | null;
    activityType: 'updated' | 'published';
};

export type GameDownloadLink = {
    id: number;
    label: string;
    url: string;
};

export type GameRelease = {
    id: number;
    title: string | null;
    platforms: GamePlatform[];
    languages: GameLanguage[];
    version: string | null;
    fileSize: string | null;
    description: string;
    publishedAt: string | null;
    downloadLinks: GameDownloadLink[];
};

export type GameDetail = GameCard & {
    cover: string;
    subtitle: string | null;
    description: string;
    developer: string;
    releaseDate: string | null;
    downloads: number;
    screenshots: string[];
    releases: GameRelease[];
    hasDownloads: boolean;
    isFavorited: boolean;
    adminEditUrl: string | null;
};
