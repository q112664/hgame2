import { useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

type Props = {
    siteKey: string;
    error?: string;
    className?: string;
    /** Reset the widget when this value changes (e.g. after a failed submit). */
    resetKey?: string | number;
    /**
     * Notifies the parent whenever a usable token is issued or cleared.
     * Use this to disable submit until verification succeeds.
     */
    onTokenChange?: (token: string | null) => void;
};

declare global {
    interface Window {
        turnstile?: {
            render: (
                element: HTMLElement,
                options: {
                    sitekey: string;
                    callback?: (token: string) => void;
                    'expired-callback'?: () => void;
                    'error-callback'?: () => void;
                    'timeout-callback'?: () => void;
                    theme?: 'auto' | 'light' | 'dark';
                    size?: 'normal' | 'flexible' | 'compact';
                },
            ) => string;
            reset: (widgetId?: string) => void;
            remove: (widgetId?: string) => void;
        };
        onTurnstileLoad?: () => void;
    }
}

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad';

let scriptLoadPromise: Promise<void> | null = null;

function loadTurnstileScript(): Promise<void> {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    if (window.turnstile) {
        return Promise.resolve();
    }

    if (scriptLoadPromise) {
        return scriptLoadPromise;
    }

    scriptLoadPromise = new Promise((resolve) => {
        const existing = document.getElementById(SCRIPT_ID);

        if (existing) {
            window.onTurnstileLoad = () => resolve();

            if (window.turnstile) {
                resolve();
            }

            return;
        }

        window.onTurnstileLoad = () => resolve();

        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    });

    return scriptLoadPromise;
}

/**
 * Cloudflare Turnstile widget that writes a hidden form field
 * `cf-turnstile-response` for Inertia / classic form posts.
 *
 * Parents should gate submit on `onTokenChange` so users cannot click through
 * before a token is ready (or after it expires).
 */
export function TurnstileWidget({
    siteKey,
    error,
    className,
    resetKey,
    onTokenChange,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const tokenInputId = useId();
    const tokenInputRef = useRef<HTMLInputElement>(null);
    const onTokenChangeRef = useRef(onTokenChange);
    const [verified, setVerified] = useState(false);

    useEffect(() => {
        onTokenChangeRef.current = onTokenChange;
    }, [onTokenChange]);

    useEffect(() => {
        let cancelled = false;
        const tokenInput = tokenInputRef.current;

        const clearToken = () => {
            if (tokenInput) {
                tokenInput.value = '';
            }

            if (!cancelled) {
                setVerified(false);
            }

            onTokenChangeRef.current?.(null);
        };

        // Tokens are single-use, so never carry one into a fresh widget.
        clearToken();

        const mount = async () => {
            await loadTurnstileScript();

            if (cancelled || !containerRef.current || !window.turnstile) {
                return;
            }

            if (widgetIdRef.current) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }

            containerRef.current.innerHTML = '';

            widgetIdRef.current = window.turnstile.render(
                containerRef.current,
                {
                    sitekey: siteKey,
                    theme: 'auto',
                    size: 'flexible',
                    callback: (token) => {
                        if (tokenInput) {
                            tokenInput.value = token;
                        }

                        setVerified(true);
                        onTokenChangeRef.current?.(token);
                    },
                    'expired-callback': () => {
                        clearToken();
                    },
                    'error-callback': () => {
                        clearToken();
                    },
                    'timeout-callback': () => {
                        clearToken();
                    },
                },
            );
        };

        void mount();

        return () => {
            cancelled = true;
            clearToken();

            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }
        };
    }, [siteKey, resetKey]);

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <input
                ref={tokenInputRef}
                id={tokenInputId}
                type="hidden"
                name="cf-turnstile-response"
                defaultValue=""
            />
            <div ref={containerRef} className="min-h-[65px] w-full" />
            <InputError message={error} />
            {!error && !verified ? (
                <p className="text-xs text-muted-foreground" role="status">
                    Complete the security check to continue.
                </p>
            ) : null}
        </div>
    );
}
