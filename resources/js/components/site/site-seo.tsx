import { Head, usePage } from '@inertiajs/react';

/**
 * Site-wide default meta tags from admin SEO settings.
 *
 * head-key values MUST match Blade data-inertia attributes in app.blade.php
 * so CSR fallbacks are adopted (not duplicated) on hydrate.
 */
export function SiteSeo() {
    const { seo, siteTitle } = usePage().props;
    const description = seo.description.trim();
    const keywords = seo.keywords.trim();
    const ogImageUrl = seo.ogImageUrl?.trim() || null;
    const customFavicon = seo.faviconUrl?.trim() || null;
    const faviconHref = customFavicon || '/favicon.ico';
    const appleTouchHref = customFavicon || '/apple-touch-icon.png';
    const googleVerification = seo.googleSiteVerification.trim();

    return (
        <Head>
            <link
                head-key="favicon"
                rel="icon"
                href={faviconHref}
                sizes="any"
            />
            <link
                head-key="apple-touch-icon"
                rel="apple-touch-icon"
                href={appleTouchHref}
            />
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
                head-key="rating"
                name="rating"
                content="RTA-5042-1996-1400-1577-RTA"
            />
            <meta head-key="rating-adult" name="rating" content="adult" />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={siteTitle}
            />
            <meta head-key="og:type" property="og:type" content="website" />
            <meta head-key="og:title" property="og:title" content={siteTitle} />
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
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={siteTitle}
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
