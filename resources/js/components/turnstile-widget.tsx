import { useEffect, useId, useRef } from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

type Props = {
    siteKey: string;
    error?: string;
    className?: string;
    /** Reset the widget when this value changes (e.g. after a failed submit). */
    resetKey?: string | number;
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
                    theme?: 'auto' | 'light' | 'dark';
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
 */
export function TurnstileWidget({
    siteKey,
    error,
    className,
    resetKey,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const tokenInputId = useId();
    const tokenInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        let cancelled = false;

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
                    callback: (token) => {
                        if (tokenInputRef.current) {
                            tokenInputRef.current.value = token;
                        }
                    },
                    'expired-callback': () => {
                        if (tokenInputRef.current) {
                            tokenInputRef.current.value = '';
                        }
                    },
                    'error-callback': () => {
                        if (tokenInputRef.current) {
                            tokenInputRef.current.value = '';
                        }
                    },
                },
            );
        };

        void mount();

        return () => {
            cancelled = true;

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
            <div ref={containerRef} className="min-h-[65px]" />
            <InputError message={error} />
        </div>
    );
}
