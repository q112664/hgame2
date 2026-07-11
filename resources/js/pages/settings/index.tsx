import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import SettingsController from '@/actions/App/Http/Controllers/Settings/SettingsController';
import AppearanceTabs from '@/components/appearance-tabs';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { ProfileAvatarForm } from '@/components/profile-avatar-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type SettingsTab = 'profile' | 'security' | 'appearance';

type PageProps = {
    auth: Auth;
};

type Props = {
    activeTab: SettingsTab;
    mustVerifyEmail: boolean;
    passwordRules?: string;
    requiresPasswordConfirmation: boolean;
    status?: string;
} &
    ManagePasskeysProps &
    ManageTwoFactorProps;

const settingsTabs: Array<{
    value: SettingsTab;
    label: string;
    href: string;
}> = [
    { value: 'profile', label: 'Profile', href: editProfile().url },
    { value: 'security', label: 'Security', href: editSecurity().url },
    { value: 'appearance', label: 'Appearance', href: editAppearance().url },
];

function isSettingsTab(value: unknown): value is SettingsTab {
    return settingsTabs.some((tab) => tab.value === value);
}

const tabTriggerClassName = cn(
    'relative z-10 h-9 rounded-lg border-transparent px-4 text-sm font-medium shadow-none',
    'text-muted-foreground transition-colors',
    'hover:bg-transparent hover:text-foreground/80',
    'data-active:border-transparent data-active:bg-transparent data-active:text-foreground data-active:shadow-none',
    'data-active:hover:bg-transparent data-active:hover:text-foreground',
    'group-data-[variant=default]/tabs-list:data-active:shadow-none',
    'dark:hover:text-foreground/80 dark:data-active:border-transparent dark:data-active:bg-transparent',
    'dark:data-active:hover:bg-transparent dark:data-active:hover:text-foreground',
);

export default function Settings(props: Props) {
    const { auth } = usePage<PageProps>().props;
    const shouldReduceMotion = useReducedMotion();
    const activeTab = props.activeTab;
    const [visualActiveTab, setVisualActiveTab] =
        useState<SettingsTab>(activeTab);
    const tabsListRef = useRef<HTMLDivElement>(null);
    const tabRefs = useRef<Partial<Record<SettingsTab, HTMLElement | null>>>(
        {},
    );
    const [pill, setPill] = useState({ left: 0, width: 0, ready: false });

    useEffect(() => {
        const resetVisualActiveTab = () => setVisualActiveTab(activeTab);
        const removeNavigateListener = router.on('navigate', (event) => {
            const page = event.detail.page;
            const nextActiveTab = page.props.activeTab;

            if (
                page.component === 'settings/index' &&
                isSettingsTab(nextActiveTab)
            ) {
                setVisualActiveTab(nextActiveTab);
            }
        });
        const removeHttpExceptionListener = router.on(
            'httpException',
            resetVisualActiveTab,
        );
        const removeNetworkErrorListener = router.on(
            'networkError',
            resetVisualActiveTab,
        );

        return () => {
            removeNavigateListener();
            removeHttpExceptionListener();
            removeNetworkErrorListener();
        };
    }, [activeTab]);

    useLayoutEffect(() => {
        const updatePill = () => {
            const list = tabsListRef.current;
            const activeTrigger = tabRefs.current[visualActiveTab];

            if (!list || !activeTrigger) {
                return;
            }

            const listRect = list.getBoundingClientRect();
            const triggerRect = activeTrigger.getBoundingClientRect();

            setPill({
                left: triggerRect.left - listRect.left,
                width: triggerRect.width,
                ready: true,
            });
        };

        updatePill();
        window.addEventListener('resize', updatePill);

        return () => window.removeEventListener('resize', updatePill);
    }, [visualActiveTab]);

    return (
        <SiteLayout>
            <Head title="Settings" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <header className="space-y-1">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground">
                        Settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage your profile, account security, and appearance.
                    </p>
                </header>

                <Tabs value={activeTab} className="gap-4">
                    <TabsList
                        ref={tabsListRef}
                        className="relative grid h-auto w-full grid-cols-3 gap-0.5 rounded-xl bg-card p-1 ring-1 ring-foreground/10 group-data-horizontal/tabs:h-auto sm:inline-grid sm:w-auto"
                    >
                        {pill.ready ? (
                            <motion.span
                                aria-hidden
                                className="absolute top-1 bottom-1 rounded-lg bg-muted"
                                initial={false}
                                animate={{ left: pill.left, width: pill.width }}
                                transition={
                                    shouldReduceMotion
                                        ? { duration: 0 }
                                        : {
                                              type: 'tween',
                                              duration: 0.2,
                                              ease: 'easeInOut',
                                          }
                                }
                            />
                        ) : null}
                        {settingsTabs.map((tab) => (
                            <TabsTrigger
                                key={tab.value}
                                value={tab.value}
                                asChild
                                className={tabTriggerClassName}
                                ref={(node) => {
                                    tabRefs.current[tab.value] = node;
                                }}
                            >
                                <Link
                                    href={tab.href}
                                    preserveState
                                    preserveScroll
                                    onStart={() =>
                                        setVisualActiveTab(tab.value)
                                    }
                                    onError={() =>
                                        setVisualActiveTab(activeTab)
                                    }
                                >
                                    {tab.label}
                                </Link>
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="profile">
                        <section className="space-y-8 rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                            <ProfileAvatarForm />

                            <div className="space-y-6 border-t border-foreground/10 pt-8">
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
                                                    defaultValue={auth.user.name}
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
                                                    defaultValue={auth.user.email}
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
                                                            className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors hover:decoration-current! dark:decoration-neutral-500"
                                                        >
                                                            Re-send the
                                                            verification email.
                                                        </Link>
                                                    </p>

                                                    {props.status ===
                                                    'verification-link-sent' ? (
                                                        <p className="mt-2 text-sm font-medium text-green-600">
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

                            <div className="border-t border-foreground/10 pt-8">
                                <DeleteUser />
                            </div>
                        </section>
                    </TabsContent>

                    <TabsContent value="security">
                        {props.requiresPasswordConfirmation ? (
                            <section className="space-y-6 rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
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
                            <section className="space-y-10 rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                                <div className="space-y-6">
                                    <Heading
                                        variant="small"
                                        title="Update password"
                                        description="Ensure your account is using a long, random password to stay secure"
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

                                                <div className="grid gap-2">
                                                    <Label htmlFor="password">
                                                        New password
                                                    </Label>
                                                    <PasswordInput
                                                        id="password"
                                                        name="password"
                                                        className="block w-full"
                                                        autoComplete="new-password"
                                                        placeholder="New password"
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

                                <ManageTwoFactor
                                    canManageTwoFactor={
                                        props.canManageTwoFactor
                                    }
                                    requiresConfirmation={
                                        props.requiresConfirmation
                                    }
                                    twoFactorEnabled={props.twoFactorEnabled}
                                />

                                <ManagePasskeys
                                    canManagePasskeys={props.canManagePasskeys}
                                    passkeys={props.passkeys}
                                />
                            </section>
                        )}
                    </TabsContent>

                    <TabsContent value="appearance">
                        <section className="space-y-6 rounded-md bg-card p-4 ring-1 ring-foreground/10 sm:p-5">
                            <Heading
                                variant="small"
                                title="Appearance"
                                description="Update the appearance settings for your account"
                            />
                            <AppearanceTabs />
                        </section>
                    </TabsContent>
                </Tabs>
            </div>
        </SiteLayout>
    );
}
