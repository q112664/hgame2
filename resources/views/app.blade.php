<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script so first paint matches stored preference (default: dark) --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "dark" }}';
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark =
                    appearance === 'dark' ||
                    (appearance === 'system' && prefersDark);

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        {{--
            CSR fallback SEO (when SSR is unavailable).
            data-inertia values MUST exactly match React head-key in SiteSeo/PageSeo.
            Favicon lives only here — never duplicate icon links outside this block.
            Page-specific tags (canonical, json-ld) are filled by React PageSeo.
        --}}
        <x-inertia::head>
            @php($seo = \App\Models\Setting::seo())
            @php($siteTitle = \App\Models\Setting::siteTitle())
            @php($faviconHref = filled($seo['faviconUrl']) ? $seo['faviconUrl'] : '/favicon.ico')
            @php($appleTouchHref = filled($seo['faviconUrl']) ? $seo['faviconUrl'] : '/apple-touch-icon.png')
            <title>{{ $siteTitle }}</title>
            <link data-inertia="favicon" rel="icon" href="{{ $faviconHref }}" sizes="any">
            <link data-inertia="apple-touch-icon" rel="apple-touch-icon" href="{{ $appleTouchHref }}">
            @if (filled($seo['description']))
                <meta data-inertia="description" name="description" content="{{ $seo['description'] }}">
            @endif
            @if (filled($seo['keywords']))
                <meta data-inertia="keywords" name="keywords" content="{{ $seo['keywords'] }}">
            @endif
            <meta data-inertia="robots" name="robots" content="{{ $seo['robots'] }}">
            <meta data-inertia="og:site_name" property="og:site_name" content="{{ $siteTitle }}">
            <meta data-inertia="og:type" property="og:type" content="website">
            <meta data-inertia="og:title" property="og:title" content="{{ $siteTitle }}">
            @if (filled($seo['description']))
                <meta data-inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
            @endif
            @if (filled($seo['ogImageUrl']))
                <meta data-inertia="og:image" property="og:image" content="{{ $seo['ogImageUrl'] }}">
            @endif
            <meta data-inertia="twitter:card" name="twitter:card" content="{{ filled($seo['ogImageUrl']) ? 'summary_large_image' : 'summary' }}">
            <meta data-inertia="twitter:title" name="twitter:title" content="{{ $siteTitle }}">
            @if (filled($seo['description']))
                <meta data-inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
            @endif
            @if (filled($seo['ogImageUrl']))
                <meta data-inertia="twitter:image" name="twitter:image" content="{{ $seo['ogImageUrl'] }}">
            @endif
            @if (filled($seo['googleSiteVerification']))
                <meta data-inertia="google-site-verification" name="google-site-verification" content="{{ $seo['googleSiteVerification'] }}">
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
