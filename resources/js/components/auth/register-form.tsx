import { Form, usePage } from '@inertiajs/react';
import SocialLoginButtons from '@/components/auth/social-login-buttons';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { TurnstileWidget } from '@/components/turnstile-widget';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTurnstileGate } from '@/hooks/use-turnstile-gate';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { SocialProvider } from '@/types/auth';

const inlineButtonClassName =
    'text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current';

type Props = {
    passwordRules: string;
    socialProviders?: SocialProvider[];
    redirect?: string;
    onLogin?: () => void;
    onSuccess?: () => void;
};

export default function RegisterForm({
    passwordRules,
    socialProviders,
    redirect,
    onLogin,
    onSuccess,
}: Props) {
    const page = usePage();
    const { turnstile } = page.props;
    const showTurnstile = Boolean(turnstile.register && turnstile.siteKey);
    const turnstileGate = useTurnstileGate(showTurnstile);
    const enabledSocialProviders =
        socialProviders ?? page.props.authModal?.socialProviders ?? [];

    return (
        <div className="flex flex-col gap-6">
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                onSuccess={onSuccess}
                onBefore={turnstileGate.onBefore}
                onError={turnstileGate.reset}
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
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            {showTurnstile && turnstile.siteKey ? (
                                <TurnstileWidget
                                    siteKey={turnstile.siteKey}
                                    error={
                                        errors['cf-turnstile-response'] as
                                            string | undefined
                                    }
                                    resetKey={turnstileGate.resetKey}
                                    onTokenChange={turnstileGate.onTokenChange}
                                />
                            ) : null}

                            <Button
                                type="submit"
                                variant="auth"
                                className="mt-2 w-full"
                                tabIndex={5}
                                disabled={
                                    processing || turnstileGate.submitDisabled
                                }
                                title={turnstileGate.submitTitle}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            {onLogin ? (
                                <button
                                    type="button"
                                    className={inlineButtonClassName}
                                    onClick={onLogin}
                                    tabIndex={6}
                                >
                                    Log in
                                </button>
                            ) : (
                                <TextLink href={login()} tabIndex={6}>
                                    Log in
                                </TextLink>
                            )}
                        </div>
                    </>
                )}
            </Form>

            <SocialLoginButtons
                providers={enabledSocialProviders}
                redirect={redirect}
            />
        </div>
    );
}
