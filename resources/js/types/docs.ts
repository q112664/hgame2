export type DocListItem = {
    slug: string;
    title: string;
    excerpt: string;
    thumbnail: string | null;
    publishedAt: string | null;
};

export type DocArticle = DocListItem & {
    body: string;
};
