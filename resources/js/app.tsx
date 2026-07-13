import { createInertiaApp } from '@inertiajs/react';
import { createRoot, hydrateRoot, type Root } from 'react-dom/client';
import { StrictMode, type ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

declare global {
    interface Window {
        __INERTIA_APP_ROOT__?: Root;
    }
}

function FlashToastListener() {
    useFlashToast();

    return null;
}

function AppShell({ children }: { children: ReactNode }) {
    return (
        <StrictMode>
            <TooltipProvider delayDuration={0}>
                <FlashToastListener />
                {children}
                <Toaster />
            </TooltipProvider>
        </StrictMode>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'search':
            case name.startsWith('resources/'):
            case name === 'settings/index':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            default:
                return AppLayout;
        }
    },
    setup({ el, App, props }) {
        const app = (
            <AppShell>
                <App {...props} />
            </AppShell>
        );

        // SSR render path (no DOM element).
        if (!el) {
            return app;
        }

        // Reuse the existing root across Vite HMR / double bootstraps.
        if (window.__INERTIA_APP_ROOT__) {
            window.__INERTIA_APP_ROOT__.render(app);

            return;
        }

        if (el.hasAttribute('data-server-rendered')) {
            window.__INERTIA_APP_ROOT__ = hydrateRoot(el, app);
        } else {
            const root = createRoot(el);
            root.render(app);
            window.__INERTIA_APP_ROOT__ = root;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
