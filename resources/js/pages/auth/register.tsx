import RegisterForm from '@/components/auth/register-form';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import type { SocialProvider } from '@/types/auth';

type Props = {
    passwordRules: string;
    socialProviders?: SocialProvider[];
    pageSeo?: PageSeoData | null;
};

export default function Register({
    passwordRules,
    socialProviders = [],
    pageSeo,
}: Props) {
    return (
        <>
            <PageSeo seo={pageSeo} title="Register" />
            <RegisterForm
                passwordRules={passwordRules}
                socialProviders={socialProviders}
            />
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
