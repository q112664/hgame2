import { Check, Share2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { heroActionIdleClassName } from '@/components/site/resource-detail-styles';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ShareButtonProps = {
    /** Absolute URL to copy. Defaults to the page the button is rendered on. */
    url?: string;
    showLabel?: boolean;
    className?: string;
};

/**
 * Copies a link, preferring the async clipboard API and falling back to the
 * `execCommand` path: `navigator.clipboard` is unavailable on insecure origins
 * (plain http, which is how the site runs locally) and when permission is denied.
 */
async function writeToClipboard(text: string): Promise<boolean> {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Fall through to the legacy path.
        }
    }

    try {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.opacity = '0';

        document.body.appendChild(field);
        field.select();

        const copied = document.execCommand('copy');
        document.body.removeChild(field);

        return copied;
    } catch {
        return false;
    }
}

/**
 * "Share" control beside the favorite toggle: copies the resource URL and
 * confirms inline (icon + label) so the click never feels like it did nothing.
 */
export function ShareButton({
    url,
    showLabel = false,
    className,
}: ShareButtonProps) {
    const [copied, setCopied] = useState(false);
    const timeout = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (timeout.current !== null) {
                window.clearTimeout(timeout.current);
            }
        },
        [],
    );

    const copyLink = useCallback(async () => {
        const link =
            url ?? (typeof window === 'undefined' ? '' : window.location.href);

        if (link === '') {
            return;
        }

        const copiedOk = await writeToClipboard(link);

        if (!copiedOk) {
            toast.error('Could not copy the link');

            return;
        }

        setCopied(true);
        toast.success('Link copied');

        if (timeout.current !== null) {
            window.clearTimeout(timeout.current);
        }

        timeout.current = window.setTimeout(() => {
            setCopied(false);
            timeout.current = null;
        }, 2000);
    }, [url]);

    const label = copied ? 'Link copied' : 'Copy link to this page';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size={showLabel ? 'default' : 'icon-lg'}
                    aria-label={label}
                    onClick={copyLink}
                    className={cn(
                        heroActionIdleClassName,
                        'cursor-pointer',
                        'hover:bg-primary/10 hover:text-primary dark:hover:bg-primary/20 dark:hover:text-primary',
                        // Icon-only on phones: the hero already carries the download
                        // and favorite labels, and the third one crowds the row.
                        showLabel &&
                            'h-9 w-9 shrink-0 px-0 sm:w-auto sm:gap-1.5 sm:px-3.5',
                        className,
                    )}
                >
                    {copied ? (
                        <Check className="size-5" />
                    ) : (
                        <Share2 className="size-5 dark:opacity-90" />
                    )}
                    {showLabel ? (
                        <span className="hidden sm:inline">
                            {copied ? 'Copied' : 'Share'}
                        </span>
                    ) : null}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
