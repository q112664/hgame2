import { Head } from '@inertiajs/react';

export type PageSeoData = {
    title?: string | null;
    description?: string | null;
    canonical?: string | null;
    robots?: string | null;
    ogType?: string | null;
    ogImageUrl?: string | null;
    jsonLd?: Record<string, unknown> | Array<Record<string, unknown>> | null;
};

type Props = {
    seo?: PageSeoData | null;
    /** Fallback document title when seo.title is empty. */
    title?: string;
};

/**
 * Serialize JSON-LD so a closing </script> in content cannot break out of the tag.
 */
function serializeJsonLd(
    value: Record<string, unknown> | Array<Record<string, unknown>>,
): string {
    return JSON.stringify(value).replace(/</g, '\\u003c');
}

/**
 * Page-level SEO overrides (description, canonical, OG, JSON-LD).
 *
 * Uses the same head-key set as SiteSeo so Inertia replaces defaults instead of
 * stacking tags after SSR hydration. Keep every Head child as a direct native
 * element because Inertia's SSR serializer does not flatten React fragments.
 * JSON-LD always uses a single head-key.
 */
export function PageSeo({ seo, title }: Props) {
    if (!seo && (title === undefined || title === '')) {
        return null;
    }

    const documentTitle = seo?.title?.trim() || title || undefined;
    const description = seo?.description?.trim() || '';
    const canonical = seo?.canonical?.trim() || '';
    const robots = seo?.robots?.trim() || '';
    const ogType = seo?.ogType?.trim() || 'website';
    const ogImageUrl = seo?.ogImageUrl?.trim() || '';
    const jsonLd = seo?.jsonLd ?? null;

    return (
        <Head title={documentTitle}>
            {description !== '' ? (
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
            ) : null}
            {robots !== '' ? (
                <meta head-key="robots" name="robots" content={robots} />
            ) : null}
            {canonical !== '' ? (
                <link head-key="canonical" rel="canonical" href={canonical} />
            ) : null}
            {documentTitle ? (
                <meta
                    head-key="og:title"
                    property="og:title"
                    content={documentTitle}
                />
            ) : null}
            <meta head-key="og:type" property="og:type" content={ogType} />
            {description !== '' ? (
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={description}
                />
            ) : null}
            {canonical !== '' ? (
                <meta head-key="og:url" property="og:url" content={canonical} />
            ) : null}
            {ogImageUrl !== '' ? (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={ogImageUrl}
                />
            ) : null}
            {documentTitle ? (
                <meta
                    head-key="twitter:title"
                    name="twitter:title"
                    content={documentTitle}
                />
            ) : null}
            {description !== '' ? (
                <meta
                    head-key="twitter:description"
                    name="twitter:description"
                    content={description}
                />
            ) : null}
            {ogImageUrl !== '' ? (
                <meta
                    head-key="twitter:card"
                    name="twitter:card"
                    content="summary_large_image"
                />
            ) : null}
            {ogImageUrl !== '' ? (
                <meta
                    head-key="twitter:image"
                    name="twitter:image"
                    content={ogImageUrl}
                />
            ) : null}
            {jsonLd ? (
                <script
                    head-key="json-ld"
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{
                        __html: serializeJsonLd(jsonLd),
                    }}
                />
            ) : null}
        </Head>
    );
}
