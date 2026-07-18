export type DocListItem = {
    slug: string;
    title: string;
    excerpt: string;
    category: string;
    publishedAt: string;
    updatedAt: string;
    readingMinutes: number;
};

export type DocHeading = {
    id: string;
    title: string;
};

export type DocArticle = DocListItem & {
    body: string;
    headings: DocHeading[];
};
