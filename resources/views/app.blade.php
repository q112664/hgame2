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

        @php($faviconUrl = \App\Models\Setting::faviconUrl())
        @if (filled($faviconUrl))
            <link rel="icon" href="{{ $faviconUrl }}">
            <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        <x-inertia::head>
            @php($seo = \App\Models\Setting::seo())
            <title>{{ \App\Models\Setting::siteTitle() }}</title>
            @if (filled($seo['description']))
                <meta name="description" content="{{ $seo['description'] }}">
            @endif
            @if (filled($seo['keywords']))
                <meta name="keywords" content="{{ $seo['keywords'] }}">
            @endif
            <meta name="robots" content="{{ $seo['robots'] }}">
            @if (filled($seo['ogImageUrl']))
                <meta property="og:image" content="{{ $seo['ogImageUrl'] }}">
            @endif
            @if (filled($seo['googleSiteVerification']))
                <meta name="google-site-verification" content="{{ $seo['googleSiteVerification'] }}">
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
