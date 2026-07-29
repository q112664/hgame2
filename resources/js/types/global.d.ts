import type { Auth, AuthModalConfig, TurnstileConfig } from '@/types/auth';
import type { NavigationMenuItem } from '@/types/navigation';
import type { NotificationsShared } from '@/types/notifications';
import type { FlashToast } from '@/types/ui';

export type SiteLogo = {
    mode: 'text' | 'image' | 'both';
    text: string;
    imageUrl: string | null;
};

export type SiteSeo = {
    description: string;
    keywords: string;
    robots: string;
    ogImageUrl: string | null;
    googleSiteVerification: string;
};

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            createdCommentId?: number;
            [key: string]: unknown;
        };
        sharedPageProps: {
            name: string;
            siteTitle: string;
            seo: SiteSeo;
            siteLogo: SiteLogo;
            navigationMenu: NavigationMenuItem[];
            turnstile: TurnstileConfig;
            auth: Auth;
            authModal: AuthModalConfig | null;
            notifications: NotificationsShared;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
