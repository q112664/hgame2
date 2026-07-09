export type MockResource = {
    id: string;
    title: string;
    thumbnail: string;
    category: string;
    platform: string;
    language: string;
    tags: string[];
    publishedAt: string;
    views: number;
};

export type MockResourceDetail = MockResource & {
    description: string;
    developer: string;
    releaseDate: string;
    fileSize: string;
    downloads: number;
};
