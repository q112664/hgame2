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
    thumbnailFallback: string;
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
    /** When downloads/packages last changed on this site (null if never). */
    downloadsUpdatedAt: string | null;
    views: number;
    hasDownloadUpdate?: boolean;
};

export type GameDownloadLink = {
    id: number;
    label: string;
    url: string;
};

export type GameReleaseContributor = {
    id: number;
    name: string;
    avatar: string | null;
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
    /** Site user who contributed this release package. */
    contributor: GameReleaseContributor | null;
    downloadLinks: GameDownloadLink[];
};

export type GameDetailVersion = {
    code: string;
    name: string;
    html: string;
    isDefault: boolean;
};

export type GameDetail = GameCard & {
    cover: string;
    subtitle: string | null;
    description: string;
    detailVersions: GameDetailVersion[];
    developer: string;
    releaseDate: string | null;
    downloadsUpdatedAt: string | null;
    downloads: number;
    /** Unique site contributors across available download packages. */
    contributors: GameReleaseContributor[];
    screenshots: string[];
    releases: GameRelease[];
    hasDownloads: boolean;
    isFavorited: boolean;
    isLiked: boolean;
    likesCount: number;
    adminEditUrl: string | null;
};
