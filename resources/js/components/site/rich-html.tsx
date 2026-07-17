import { cn } from '@/lib/utils';

type Props = {
    html: string;
    className?: string;
    onImageClick?: (src: string, index: number, sources: string[]) => void;
};

/**
 * Renders Filament RichEditor HTML with Tailwind Typography defaults.
 * Preflight resets heading/paragraph/list styles; prose restores readable layout.
 */
export function RichHtml({ html, className, onImageClick }: Props) {
    return (
        <div
            className={cn(
                'prose max-w-none prose-headings:text-foreground prose-p:text-foreground prose-blockquote:text-muted-foreground prose-strong:text-foreground prose-code:text-foreground prose-li:text-foreground prose-hr:border-border',
                'prose-a:text-info prose-img:rounded-sm',
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
