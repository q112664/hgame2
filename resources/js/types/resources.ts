export type GamePlatform = {
    name: string;
    slug: string;
};

export type GameCard = {
    id: string;
    title: string;
    thumbnail: string;
    category: string;
    platforms: GamePlatform[];
    languages: string[];
    tags: string[];
    publishedAt: string | null;
    views: number;
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
    subtitle: string | null;
    description: string;
    developer: string;
    releaseDate: string | null;
    downloads: number;
    screenshots: string[];
    releases: GameRelease[];
};
