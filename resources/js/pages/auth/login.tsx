import LoginForm from '@/components/auth/login-form';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import type { SocialProvider } from '@/types/auth';

type Props = {
    status?: string;
    canRegister?: boolean;
    canResetPassword: boolean;
    canUsePasskeys?: boolean;
    socialProviders?: SocialProvider[];
    pageSeo?: PageSeoData | null;
};

export default function Login({
    status,
    canRegister = true,
    canResetPassword,
    canUsePasskeys = false,
    socialProviders = [],
    pageSeo,
}: Props) {
    return (
        <>
            <PageSeo seo={pageSeo} title="Log in" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <LoginForm
                canRegister={canRegister}
                canResetPassword={canResetPassword}
                canUsePasskeys={canUsePasskeys}
                socialProviders={socialProviders}
            />
        </>
    );
}
