import type { Page } from '@inertiajs/core';
import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import type { ReactNode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import AuthModalLayout from '@/layouts/auth-modal-layout';

const fallbackSiteTitle = import.meta.env.VITE_APP_NAME || 'hgame';

declare global {
    interface Window {
        __INERTIA_APP_ROOT__?: Root;
    }
}

function resolveSiteTitle(page?: Page): string {
    const shared = page?.props?.siteTitle;

    return typeof shared === 'string' && shared.trim() !== ''
        ? shared.trim()
        : fallbackSiteTitle;
}

function resolvePageTitleSuffix(page?: Page): string | null {
    const pageSeo = page?.props?.pageSeo;

    if (typeof pageSeo !== 'object' || pageSeo === null) {
        return null;
    }

    const titleSuffix = Reflect.get(pageSeo, 'titleSuffix');

    return typeof titleSuffix === 'string' && titleSuffix.trim() !== ''
        ? titleSuffix.trim()
        : null;
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

// Apply stored theme before the first React paint so toggle icons match.
initializeTheme();

createInertiaApp({
    title: (title, page) => {
        const pageTitleSuffix = resolvePageTitleSuffix(page);

        if (title && pageTitleSuffix) {
            return `${title} | ${pageTitleSuffix}`;
        }

        const siteTitle = resolveSiteTitle(page);

        return title ? `${title} - ${siteTitle}` : siteTitle;
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'search':
            case name === 'favorites':
            case name.startsWith('notifications/'):
            case name.startsWith('resources/'):
            case name.startsWith('users/'):
            case name.startsWith('docs/'):
            case name.startsWith('download-links/'):
            case name === 'settings/index':
                return AuthModalLayout;
            case name.startsWith('auth/'):
                return [AuthModalLayout, AuthLayout];
            default:
                return [AuthModalLayout, AppLayout];
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
