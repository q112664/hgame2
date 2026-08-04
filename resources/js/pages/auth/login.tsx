import LoginForm from '@/components/auth/login-form';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';

type Props = {
    status?: string;
    canRegister?: boolean;
    canResetPassword: boolean;
    canUsePasskeys?: boolean;
    pageSeo?: PageSeoData | null;
};

export default function Login({
    status,
    canRegister = true,
    canResetPassword,
    canUsePasskeys = false,
    pageSeo,
}: Props) {
    return (
        <>
            <PageSeo seo={pageSeo} title="Log in" />

            <LoginForm
                canRegister={canRegister}
                canResetPassword={canResetPassword}
                canUsePasskeys={canUsePasskeys}
            />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}
