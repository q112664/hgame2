import { Head } from '@inertiajs/react';
import LoginForm from '@/components/auth/login-form';

type Props = {
    status?: string;
    canRegister?: boolean;
    canResetPassword: boolean;
};

export default function Login({
    status,
    canRegister = true,
    canResetPassword,
}: Props) {
    return (
        <>
            <Head title="Log in" />

            <LoginForm
                canRegister={canRegister}
                canResetPassword={canResetPassword}
            />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
