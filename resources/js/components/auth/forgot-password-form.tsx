import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

const inlineButtonClassName =
    'text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current';

type Props = {
    status?: string;
    onLogin?: () => void;
};

export default function ForgotPasswordForm({ status, onLogin }: Props) {
    const [sent, setSent] = useState(false);

    return (
        <div className="space-y-6">
            {status || sent ? (
                <p
                    className="text-center text-sm font-medium text-success"
                    role="status"
                >
                    {status ??
                        'If an account exists for that email, a password reset link has been sent.'}
                </p>
            ) : null}

            <Form
                {...email.form()}
                onStart={() => setSent(false)}
                onSuccess={() => setSent(true)}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                placeholder="email@example.com"
                            />

                            <InputError message={errors.email} />
                        </div>

                        <div className="my-6 flex items-center justify-start">
                            <Button
                                variant="auth"
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Email password reset link
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <div className="space-x-1 text-center text-sm text-muted-foreground">
                <span>Or, return to</span>{' '}
                {onLogin ? (
                    <button
                        type="button"
                        className={inlineButtonClassName}
                        onClick={onLogin}
                    >
                        log in
                    </button>
                ) : (
                    <TextLink href={login()}>log in</TextLink>
                )}
            </div>
        </div>
    );
}
