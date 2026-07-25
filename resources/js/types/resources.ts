export type GamePlatform = {
    name: string;
    slug: string;
};

export type GameTag = {
    name: string;
    slug: string;
};

export type GameCard = {
    id: string;
    title: string;
    subtitle: string | null;
    thumbnail: string;
    category: string;
    developer: string;
    platforms: GamePlatform[];
    languages: string[];
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
    languages: string[];
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
    languages: string[];
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
    ratingAverage: number | null;
    ratingCount: number;
    userRating: number | null;
    adminEditUrl: string | null;
};
