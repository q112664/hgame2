import { Head, usePage } from '@inertiajs/react';

/**
 * Site-wide default meta tags from admin SEO settings.
 * Page-level <Head> entries with the same head-key override these.
 */
export function SiteSeo() {
    const { seo, siteTitle } = usePage().props;
    const description = seo.description.trim();
    const keywords = seo.keywords.trim();
    const ogImageUrl = seo.ogImageUrl?.trim() || null;
    const googleVerification = seo.googleSiteVerification.trim();

    return (
        <Head>
            {description !== '' ? (
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
            ) : null}
            {keywords !== '' ? (
                <meta head-key="keywords" name="keywords" content={keywords} />
            ) : null}
            <meta head-key="robots" name="robots" content={seo.robots} />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={siteTitle}
            />
            <meta head-key="og:type" property="og:type" content="website" />
            {description !== '' ? (
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={description}
                />
            ) : null}
            {ogImageUrl ? (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={ogImageUrl}
                />
            ) : null}
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={ogImageUrl ? 'summary_large_image' : 'summary'}
            />
            {description !== '' ? (
                <meta
                    head-key="twitter:description"
                    name="twitter:description"
                    content={description}
                />
            ) : null}
            {ogImageUrl ? (
                <meta
                    head-key="twitter:image"
                    name="twitter:image"
                    content={ogImageUrl}
                />
            ) : null}
            {googleVerification !== '' ? (
                <meta
                    head-key="google-site-verification"
                    name="google-site-verification"
                    content={googleVerification}
                />
            ) : null}
        </Head>
    );
}
