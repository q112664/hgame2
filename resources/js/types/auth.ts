export type User = {
    id: number;
    slug: string;
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

export type SocialProvider = 'google' | 'discord';

export type SocialConnection = {
    provider: SocialProvider;
    label: string;
    available: boolean;
    linked: boolean;
    email: string | null;
    canUnlink: boolean;
};

export type AuthModalConfig = {
    canRegister: boolean;
    canResetPassword: boolean;
    canUsePasskeys: boolean;
    passwordRules: string;
    turnstile: TurnstileConfig;
    socialProviders: SocialProvider[];
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
