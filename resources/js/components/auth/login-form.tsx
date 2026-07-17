import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const inlineButtonClassName =
    'text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current';

type Props = {
    canRegister?: boolean;
    canResetPassword: boolean;
    redirect?: string;
    onForgotPassword?: () => void;
    onRegister?: () => void;
    onSuccess?: () => void;
};

export default function LoginForm({
    canRegister = true,
    canResetPassword,
    redirect,
    onForgotPassword,
    onRegister,
    onSuccess,
}: Props) {
    return (
        <>
            <PasskeyVerify redirect={redirect} onSuccess={onSuccess} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                onSuccess={onSuccess}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {redirect ? (
                            <input
                                type="hidden"
                                name="redirect"
                                value={redirect}
                            />
                        ) : null}

                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword ? (
                                        onForgotPassword ? (
                                            <button
                                                type="button"
                                                className={`ml-auto text-sm ${inlineButtonClassName}`}
                                                onClick={onForgotPassword}
                                                tabIndex={5}
                                            >
                                                Forgot your password?
                                            </button>
                                        ) : (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm"
                                                tabIndex={5}
                                            >
                                                Forgot your password?
                                            </TextLink>
                                        )
                                    ) : null}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        {canRegister ? (
                            <div className="text-center text-sm text-muted-foreground">
                                Don't have an account?{' '}
                                {onRegister ? (
                                    <button
                                        type="button"
                                        className={inlineButtonClassName}
                                        onClick={onRegister}
                                        tabIndex={6}
                                    >
                                        Sign up
                                    </button>
                                ) : (
                                    <TextLink href={register()} tabIndex={6}>
                                        Sign up
                                    </TextLink>
                                )}
                            </div>
                        ) : null}
                    </>
                )}
            </Form>
        </>
    );
}
