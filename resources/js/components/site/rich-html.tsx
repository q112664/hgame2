import { cn } from '@/lib/utils';

type Props = {
    html: string;
    className?: string;
    onImageClick?: (src: string, index: number, sources: string[]) => void;
};

/**
 * Renders Filament RichEditor HTML with Tailwind Typography defaults.
 * Preflight resets heading/paragraph/list styles; prose restores readable layout.
 * Theme tokens override default prose grays so body text stays readable in dark mode
 * (including plain-text descriptions that are not wrapped in `<p>`).
 */
export function RichHtml({ html, className, onImageClick }: Props) {
    return (
        <div
            className={cn(
                'prose max-w-none',
                '[--tw-prose-body:var(--color-foreground)]',
                '[--tw-prose-headings:var(--color-foreground)]',
                '[--tw-prose-lead:var(--color-muted-foreground)]',
                '[--tw-prose-links:var(--color-info)]',
                '[--tw-prose-bold:var(--color-foreground)]',
                '[--tw-prose-counters:var(--color-muted-foreground)]',
                '[--tw-prose-bullets:var(--color-muted-foreground)]',
                '[--tw-prose-hr:var(--color-border)]',
                '[--tw-prose-quotes:var(--color-muted-foreground)]',
                '[--tw-prose-quote-borders:var(--color-border)]',
                '[--tw-prose-captions:var(--color-muted-foreground)]',
                '[--tw-prose-kbd:var(--color-foreground)]',
                '[--tw-prose-code:var(--color-foreground)]',
                '[--tw-prose-pre-code:var(--color-foreground)]',
                '[--tw-prose-pre-bg:var(--color-muted)]',
                '[--tw-prose-th-borders:var(--color-border)]',
                '[--tw-prose-td-borders:var(--color-border)]',
                'prose-img:rounded-sm',
                onImageClick && 'prose-img:cursor-zoom-in',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: html }}
            onClick={(event) => {
                if (!onImageClick) {
                    return;
                }

                const target = event.target;

                if (!(target instanceof HTMLImageElement)) {
                    return;
                }

                const root = event.currentTarget;
                const sources = Array.from(root.querySelectorAll('img'))
                    .map((image) => image.currentSrc || image.src)
                    .filter((src) => src !== '');
                const src = target.currentSrc || target.src;
                const index = sources.indexOf(src);

                if (index < 0) {
                    return;
                }

                event.preventDefault();
                onImageClick(src, index, sources);
            }}
        />
    );
}
