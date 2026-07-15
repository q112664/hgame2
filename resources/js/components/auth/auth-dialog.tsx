import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { createContext, useCallback, useContext, useState } from 'react';
import ForgotPasswordForm from '@/components/auth/forgot-password-form';
import LoginForm from '@/components/auth/login-form';
import RegisterForm from '@/components/auth/register-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AuthModalConfig } from '@/types/auth';

export type AuthDialogView = 'login' | 'register' | 'forgot-password';

type OpenAuthDialogOptions = {
    redirect?: string;
};

type AuthDialogContextValue = {
    openAuthDialog: (
        view?: AuthDialogView,
        options?: OpenAuthDialogOptions,
    ) => void;
    closeAuthDialog: () => void;
};

type Props = {
    children: ReactNode;
};

const AuthDialogContext = createContext<AuthDialogContextValue | null>(null);

const viewCopy: Record<AuthDialogView, { title: string; description: string }> =
    {
        login: {
            title: 'Log in to your account',
            description: 'Enter your email and password below to log in.',
        },
        register: {
            title: 'Create an account',
            description: 'Enter your details below to create your account.',
        },
        'forgot-password': {
            title: 'Forgot password',
            description: 'Enter your email to receive a password reset link.',
        },
    };

function resolveView(
    view: AuthDialogView,
    config: AuthModalConfig,
): AuthDialogView {
    if (view === 'register' && !config.canRegister) {
        return 'login';
    }

    if (view === 'forgot-password' && !config.canResetPassword) {
        return 'login';
    }

    return view;
}

export function useAuthDialog(): AuthDialogContextValue {
    const context = useContext(AuthDialogContext);

    if (!context) {
        throw new Error(
            'useAuthDialog must be used within an AuthDialogProvider',
        );
    }

    return context;
}

export function AuthDialogProvider({ children }: Props) {
    const page = usePage();
    const authModal = page.props.authModal;
    const [open, setOpen] = useState(false);
    const [view, setView] = useState<AuthDialogView>('login');
    const [redirect, setRedirect] = useState<string>();

    const closeAuthDialog = useCallback(() => {
        setOpen(false);
    }, []);

    const openAuthDialog = useCallback(
        (
            requestedView: AuthDialogView = 'login',
            options?: OpenAuthDialogOptions,
        ) => {
            if (!authModal) {
                return;
            }

            setView(resolveView(requestedView, authModal));
            setRedirect(options?.redirect ?? page.url);
            setOpen(true);
        },
        [authModal, page.url],
    );

    const switchView = useCallback(
        (requestedView: AuthDialogView) => {
            if (!authModal) {
                return;
            }

            setView(resolveView(requestedView, authModal));
        },
        [authModal],
    );

    return (
        <AuthDialogContext.Provider value={{ openAuthDialog, closeAuthDialog }}>
            {children}
            {authModal ? (
                <AuthDialog
                    authModal={authModal}
                    closeAuthDialog={closeAuthDialog}
                    onOpenChange={setOpen}
                    open={open}
                    redirect={redirect}
                    switchView={switchView}
                    view={view}
                />
            ) : null}
        </AuthDialogContext.Provider>
    );
}

type AuthDialogProps = {
    authModal: AuthModalConfig;
    closeAuthDialog: () => void;
    onOpenChange: (open: boolean) => void;
    open: boolean;
    redirect?: string;
    switchView: (view: AuthDialogView) => void;
    view: AuthDialogView;
};

function AuthDialog({
    authModal,
    closeAuthDialog,
    onOpenChange,
    open,
    redirect,
    switchView,
    view,
}: AuthDialogProps) {
    const copy = viewCopy[view];

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    closeAuthDialog();
                } else {
                    onOpenChange(nextOpen);
                }
            }}
        >
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{copy.title}</DialogTitle>
                    <DialogDescription>{copy.description}</DialogDescription>
                </DialogHeader>

                <div key={view}>
                    {view === 'login' ? (
                        <LoginForm
                            canRegister={authModal.canRegister}
                            canResetPassword={authModal.canResetPassword}
                            onForgotPassword={
                                authModal.canResetPassword
                                    ? () => switchView('forgot-password')
                                    : undefined
                            }
                            onRegister={
                                authModal.canRegister
                                    ? () => switchView('register')
                                    : undefined
                            }
                            onSuccess={closeAuthDialog}
                            redirect={redirect}
                        />
                    ) : view === 'register' ? (
                        <RegisterForm
                            onLogin={() => switchView('login')}
                            onSuccess={closeAuthDialog}
                            passwordRules={authModal.passwordRules}
                            redirect={redirect}
                        />
                    ) : (
                        <ForgotPasswordForm
                            onLogin={() => switchView('login')}
                        />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
