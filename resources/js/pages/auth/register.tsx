import { Head } from '@inertiajs/react';
import RegisterForm from '@/components/auth/register-form';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Register" />
            <RegisterForm passwordRules={passwordRules} />
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
