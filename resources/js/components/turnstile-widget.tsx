import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
                    'error-callback'?: (errorCode?: string) => void;
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

/** Cloudflare's script normally lands in well under a second; 12s means "stuck". */
const SCRIPT_TIMEOUT_MS = 12_000;

let scriptLoadPromise: Promise<void> | null = null;

/**
 * Drop the cached promise and the script tag so a retry really re-fetches.
 * Without this a failed load poisons every later attempt on the page.
 */
function resetTurnstileScript(): void {
    scriptLoadPromise = null;
    document.getElementById(SCRIPT_ID)?.remove();
    delete window.onTurnstileLoad;
}

/**
 * Loads `api.js` with the failure modes the browser can produce: a blocked or
 * unreachable host (`error`), and a stalled request (timeout). Both reject so
 * the widget can tell the reader what happened instead of showing a blank box.
 */
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

    scriptLoadPromise = new Promise<void>((resolve, reject) => {
        let script = document.getElementById(
            SCRIPT_ID,
        ) as HTMLScriptElement | null;

        const finish = (failure?: Error) => {
            window.clearTimeout(timer);
            script?.removeEventListener('load', onLoad);
            script?.removeEventListener('error', onError);

            if (failure) {
                resetTurnstileScript();
                reject(failure);

                return;
            }

            resolve();
        };

        const timer = window.setTimeout(
            () => finish(new Error('turnstile-timeout')),
            SCRIPT_TIMEOUT_MS,
        );

        // The API fires `onTurnstileLoad` once `window.turnstile` is ready; the
        // element's own `load` event can arrive before that.
        const onLoad = () => {
            if (window.turnstile) {
                finish();
            }
        };

        const onError = () => finish(new Error('turnstile-script-failed'));

        window.onTurnstileLoad = () => finish();

        if (!script) {
            script = document.createElement('script');
            script.id = SCRIPT_ID;
            script.src = SCRIPT_SRC;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        script.addEventListener('load', onLoad);
        script.addEventListener('error', onError);

        if (window.turnstile) {
            finish();
        }
    });

    return scriptLoadPromise;
}

type WidgetStatus = 'loading' | 'ready' | 'failed';

/**
 * Cloudflare Turnstile widget. Turnstile injects its own hidden
 * `cf-turnstile-response` input inside the rendered container, so the token
 * rides along with any normal form post; this component only mirrors the
 * verification state to the parent through `onTokenChange`.
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
    const onTokenChangeRef = useRef(onTokenChange);
    const [status, setStatus] = useState<WidgetStatus>('loading');
    const [verified, setVerified] = useState(false);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        onTokenChangeRef.current = onTokenChange;
    }, [onTokenChange]);

    useEffect(() => {
        let cancelled = false;

        const clearToken = () => {
            if (!cancelled) {
                setVerified(false);
            }

            onTokenChangeRef.current?.(null);
        };

        // Tokens are single-use, so never carry one into a fresh widget.
        clearToken();

        const mount = async () => {
            try {
                await loadTurnstileScript();
            } catch {
                if (!cancelled) {
                    setStatus('failed');
                }

                return;
            }

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
                        if (cancelled) {
                            return;
                        }

                        setVerified(true);
                        setStatus('ready');
                        onTokenChangeRef.current?.(token);
                    },
                    'expired-callback': () => {
                        clearToken();
                    },
                    'error-callback': (errorCode) => {
                        clearToken();

                        // A code here means the widget itself could not run
                        // (bad sitekey, blocked host, failed challenge): the
                        // reader needs to see something they can act on.
                        if (!cancelled) {
                            setStatus('failed');
                        }

                        if (errorCode) {
                            console.warn('Turnstile error', errorCode);
                        }
                    },
                    'timeout-callback': () => {
                        clearToken();
                    },
                },
            );

            if (!cancelled) {
                setStatus('ready');
            }
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
    }, [siteKey, resetKey, attempt]);

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <div ref={containerRef} className="min-h-[65px] w-full" />
            <InputError message={error} />
            {status === 'failed' ? (
                <div
                    className="flex flex-wrap items-center gap-2 text-xs"
                    role="alert"
                >
                    <span className="text-destructive">
                        Security check could not load. Check your connection or
                        disable ad blockers, then retry.
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setStatus('loading');
                            setAttempt((value) => value + 1);
                        }}
                    >
                        Retry
                    </Button>
                </div>
            ) : status === 'loading' ? (
                <p className="text-xs text-muted-foreground" role="status">
                    Loading security check…
                </p>
            ) : !error && !verified ? (
                <p className="text-xs text-muted-foreground" role="status">
                    Complete the security check to continue.
                </p>
            ) : null}
        </div>
    );
}
