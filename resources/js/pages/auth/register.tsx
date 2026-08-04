import RegisterForm from '@/components/auth/register-form';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';

type Props = {
    passwordRules: string;
    pageSeo?: PageSeoData | null;
};

export default function Register({ passwordRules, pageSeo }: Props) {
    return (
        <>
            <PageSeo seo={pageSeo} title="Register" />
            <RegisterForm passwordRules={passwordRules} />
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
