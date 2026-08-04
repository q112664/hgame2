import ForgotPasswordForm from '@/components/auth/forgot-password-form';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';

type Props = {
    status?: string;
    pageSeo?: PageSeoData | null;
};

export default function ForgotPassword({ status, pageSeo }: Props) {
    return (
        <>
            <PageSeo seo={pageSeo} title="Forgot password" />

            <ForgotPasswordForm status={status} />
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your email to receive a password reset link',
};
