import type { ReactNode } from 'react';
import { AuthDialogProvider } from '@/components/auth/auth-dialog';

export default function AuthModalLayout({ children }: { children: ReactNode }) {
    return <AuthDialogProvider>{children}</AuthDialogProvider>;
}
