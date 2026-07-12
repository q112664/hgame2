import { cn } from '@/lib/utils';

type Props = {
    html: string;
    className?: string;
};

/**
 * Renders Filament RichEditor HTML with Tailwind Typography defaults.
 * Preflight resets heading/paragraph/list styles; prose restores readable layout.
 */
export function RichHtml({ html, className }: Props) {
    return (
        <div
            className={cn(
                'prose prose-neutral max-w-none dark:prose-invert',
                'prose-img:rounded-md prose-a:text-sky-700 dark:prose-a:text-sky-300',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
