import { Head } from '@inertiajs/react';
import ForgotPasswordForm from '@/components/auth/forgot-password-form';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            <ForgotPasswordForm status={status} />
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your email to receive a password reset link',
};
