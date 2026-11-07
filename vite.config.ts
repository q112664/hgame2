import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/admin/theme.css',
                'resources/js/app.tsx',
            ],
            refresh: true,
            fonts: [
                // Preloading every weight would ship ~47 kB of woff2 that is never
                // rendered: the plugin declares a legacy `woff` face after each
                // `woff2` one with the same unicode-range, so the browser always
                // picks the woff. Faces stay declared for the weights in use, they
                // are simply fetched on demand instead of preloaded.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    subsets: ['latin'],
                    preload: false,
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
