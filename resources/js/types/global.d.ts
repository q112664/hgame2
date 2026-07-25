import type { Auth, AuthModalConfig } from '@/types/auth';
import type { NavigationMenuItem } from '@/types/navigation';

export type SiteLogo = {
    mode: 'text' | 'image' | 'both';
    text: string;
    imageUrl: string | null;
};

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            siteLogo: SiteLogo;
            navigationMenu: NavigationMenuItem[];
            ratingsEnabled: boolean;
            auth: Auth;
            authModal: AuthModalConfig | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
