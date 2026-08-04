import { useCallback, useState } from 'react';

/**
 * Gate form submit actions on a successful Cloudflare Turnstile token.
 *
 * Best practice (CF docs / common integrations):
 * - Keep submit disabled until the success callback provides a token
 * - Re-lock on expire / error / timeout / server-side validation failure
 * - Cancel accidental submits (e.g. Enter) via onBefore when still locked
 */
export function useTurnstileGate(enabled: boolean) {
    const [token, setToken] = useState<string | null>(null);
    const [resetKey, setResetKey] = useState(0);

    const verified = !enabled || Boolean(token);
    const locked = enabled && !token;

    const onTokenChange = useCallback((next: string | null) => {
        setToken(next && next.trim() !== '' ? next : null);
    }, []);

    const reset = useCallback(() => {
        setToken(null);
        setResetKey((key) => key + 1);
    }, []);

    const onBefore = useCallback(() => {
        if (locked) {
            return false;
        }
    }, [locked]);

    return {
        token,
        resetKey,
        verified,
        locked,
        onTokenChange,
        reset,
        onBefore,
        submitDisabled: locked,
        submitTitle: locked
            ? 'Complete the security check to continue'
            : undefined,
    };
}
