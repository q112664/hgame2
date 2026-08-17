import { Form, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import SettingsController from '@/actions/App/Http/Controllers/Settings/SettingsController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageSocialAccountsProps } from '@/components/manage-social-accounts';
import ManageSocialAccounts from '@/components/manage-social-accounts';
import PasswordInput from '@/components/password-input';
import { ProfileAvatarForm } from '@/components/profile-avatar-form';
import { PageSeo } from '@/components/site/page-seo';
import type { PageSeoData } from '@/components/site/page-seo';
import { RouteTabs } from '@/components/site/route-tabs';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SiteLayout } from '@/layouts/site-layout';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type SettingsTab = 'profile' | 'security';

type PageProps = {
    auth: Auth;
};

type Props = {
    activeTab: SettingsTab;
    mustVerifyEmail: boolean;
    passwordRules?: string;
    hasPassword?: boolean;
    requiresPasswordConfirmation: boolean;
    status?: string;
    pageSeo?: PageSeoData | null;
    canManageTwoFactor?: boolean;
} & ManagePasskeysProps &
    ManageSocialAccountsProps;

const settingsTabs: Array<{
    value: SettingsTab;
    label: string;
    href: string;
}> = [
    { value: 'profile', label: 'Profile', href: editProfile().url },
    { value: 'security', label: 'Security', href: editSecurity().url },
];

export default function Settings(props: Props) {
    const { auth } = usePage<PageProps>().props;
    const activeTab = props.activeTab;

    return (
        <SiteLayout>
            <PageSeo seo={props.pageSeo} title="Settings" />

            <SitePageContainer className="gap-6">
                <header className="space-y-1">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground">
                        Settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage your profile and account security.
                    </p>
                </header>

                <div className="flex flex-col gap-4">
                    <RouteTabs tabs={settingsTabs} activeValue={activeTab} />

                    {activeTab === 'profile' ? (
                        <section className="space-y-8 rounded-md border border-border bg-card p-4 sm:p-5">
                            <ProfileAvatarForm />

                            <div className="space-y-6 border-t border-border pt-8">
                                <Heading
                                    variant="small"
                                    title="Profile"
                                    description="Update your name and email address"
                                />

                                <Form
                                    {...ProfileController.update.form()}
                                    options={{ preserveScroll: true }}
                                    className="space-y-6"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Name
                                                </Label>
                                                <Input
                                                    id="name"
                                                    className="block w-full"
                                                    defaultValue={
                                                        auth.user.name
                                                    }
                                                    name="name"
                                                    required
                                                    autoComplete="name"
                                                    placeholder="Full name"
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="email">
                                                    Email address
                                                </Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    className="block w-full"
                                                    defaultValue={
                                                        auth.user.email
                                                    }
                                                    name="email"
                                                    required
                                                    autoComplete="username"
                                                    placeholder="Email address"
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>

                                            {props.mustVerifyEmail &&
                                            auth.user.email_verified_at ===
                                                null ? (
                                                <div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Your email address is
                                                        unverified.{' '}
                                                        <Link
                                                            href={send()}
                                                            as="button"
                                                            className="text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current!"
                                                        >
                                                            Re-send the
                                                            verification email.
                                                        </Link>
                                                    </p>

                                                    {props.status ===
                                                    'verification-link-sent' ? (
                                                        <p className="mt-2 text-sm font-medium text-success">
                                                            A new verification
                                                            link has been sent
                                                            to your email
                                                            address.
                                                        </p>
                                                    ) : null}
                                                </div>
                                            ) : null}

                                            <div className="flex items-center gap-4">
                                                <Button
                                                    disabled={processing}
                                                    data-test="update-profile-button"
                                                >
                                                    Save
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </div>
                        </section>
                    ) : null}

                    {activeTab === 'security' ? (
                        props.requiresPasswordConfirmation ? (
                            <section className="space-y-6 rounded-md border border-border bg-card p-4 sm:p-5">
                                <Heading
                                    variant="small"
                                    title="Confirm your password"
                                    description="Confirm your password to manage account security settings"
                                />

                                <Form
                                    {...SettingsController.confirmSecurity.form()}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess={['password']}
                                    className="space-y-6"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="security_confirmation_password">
                                                    Password
                                                </Label>
                                                <PasswordInput
                                                    id="security_confirmation_password"
                                                    name="password"
                                                    className="block w-full"
                                                    autoComplete="current-password"
                                                    placeholder="Password"
                                                    autoFocus
                                                />
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>

                                            <Button
                                                disabled={processing}
                                                data-test="confirm-security-password-button"
                                            >
                                                Confirm password
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </section>
                        ) : (
                            <section className="space-y-10 rounded-md border border-border bg-card p-4 sm:p-5">
                                <div className="space-y-6">
                                    <Heading
                                        variant="small"
                                        title={
                                            props.hasPassword === false
                                                ? 'Set password'
                                                : 'Update password'
                                        }
                                        description={
                                            props.hasPassword === false
                                                ? 'Add a password so you can sign in without a social account'
                                                : 'Ensure your account is using a long, random password to stay secure'
                                        }
                                    />

                                    <Form
                                        {...SecurityController.update.form()}
                                        options={{ preserveScroll: true }}
                                        resetOnError={[
                                            'password',
                                            'password_confirmation',
                                            'current_password',
                                        ]}
                                        resetOnSuccess
                                        className="space-y-6"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                {props.hasPassword !== false ? (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="current_password">
                                                            Current password
                                                        </Label>
                                                        <PasswordInput
                                                            id="current_password"
                                                            name="current_password"
                                                            className="block w-full"
                                                            autoComplete="current-password"
                                                            placeholder="Current password"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.current_password
                                                            }
                                                        />
                                                    </div>
                                                ) : null}

                                                <div className="grid gap-2">
                                                    <Label htmlFor="password">
                                                        {props.hasPassword ===
                                                        false
                                                            ? 'Password'
                                                            : 'New password'}
                                                    </Label>
                                                    <PasswordInput
                                                        id="password"
                                                        name="password"
                                                        className="block w-full"
                                                        autoComplete="new-password"
                                                        placeholder={
                                                            props.hasPassword ===
                                                            false
                                                                ? 'Password'
                                                                : 'New password'
                                                        }
                                                        passwordrules={
                                                            props.passwordRules
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.password
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="password_confirmation">
                                                        Confirm password
                                                    </Label>
                                                    <PasswordInput
                                                        id="password_confirmation"
                                                        name="password_confirmation"
                                                        className="block w-full"
                                                        autoComplete="new-password"
                                                        placeholder="Confirm password"
                                                        passwordrules={
                                                            props.passwordRules
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.password_confirmation
                                                        }
                                                    />
                                                </div>

                                                <div className="flex items-center gap-4">
                                                    <Button
                                                        disabled={processing}
                                                        data-test="update-password-button"
                                                    >
                                                        Save
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </div>

                                <ManageSocialAccounts
                                    socialConnections={props.socialConnections}
                                />

                                <ManagePasskeys
                                    canManagePasskeys={props.canManagePasskeys}
                                    passkeys={props.passkeys}
                                />

                                <DeleteUser />
                            </section>
                        )
                    ) : null}
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
