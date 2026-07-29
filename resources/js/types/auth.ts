export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null | undefined;
    email_verified_at: string | null;
    is_admin?: boolean;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TurnstileConfig = {
    siteKey: string | null;
    login: boolean;
    register: boolean;
    forgotPassword: boolean;
    download: boolean;
};

export type AuthModalConfig = {
    canRegister: boolean;
    canResetPassword: boolean;
    passwordRules: string;
    turnstile: TurnstileConfig;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
