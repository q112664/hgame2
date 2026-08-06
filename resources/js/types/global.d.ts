import type { Auth, AuthModalConfig, TurnstileConfig } from '@/types/auth';
import type {
    FooterLinkItem,
    NavigationMenuItem,
    TaxonomyNav,
} from '@/types/navigation';
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
    faviconUrl: string | null;
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
            footerLinks: FooterLinkItem[];
            taxonomyNav: TaxonomyNav;
            turnstile: TurnstileConfig;
            auth: Auth;
            authModal: AuthModalConfig | null;
            notificationSummary: NotificationsShared;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
