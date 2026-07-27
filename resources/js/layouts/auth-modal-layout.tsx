import type { ReactNode } from 'react';
import { AuthDialogProvider } from '@/components/auth/auth-dialog';
import { SiteSeo } from '@/components/site/site-seo';

export default function AuthModalLayout({ children }: { children: ReactNode }) {
    return (
        <AuthDialogProvider>
            <SiteSeo />
            {children}
        </AuthDialogProvider>
    );
}
