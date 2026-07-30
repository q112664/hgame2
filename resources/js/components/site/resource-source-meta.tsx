import type { GameSource } from '@/types/resources';

type Props = {
    source: GameSource;
};

/**
 * Source storefront meta for the resource hero — same rhythm as developer / date.
 */
export function ResourceSourceMeta({ source }: Props) {
    const text = [source.name, source.id].filter(Boolean).join(' · ');

    if (text === '' && !source.url) {
        return null;
    }

    const icon = source.faviconUrl ? (
        <img
            src={source.faviconUrl}
            alt=""
            width={14}
            height={14}
            className="size-3.5 shrink-0 object-contain"
            loading="lazy"
            decoding="async"
            referrerPolicy="no-referrer"
            aria-hidden
        />
    ) : null;

    if (source.url) {
        return (
            <a
                href={source.url}
                target="_blank"
                rel="noopener noreferrer"
                title={text || source.url}
                className="inline-flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                {icon}
                {text !== '' ? (
                    <span className="truncate">{text}</span>
                ) : null}
            </a>
        );
    }

    return (
        <span
            className="inline-flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground"
            title={text || undefined}
        >
            {icon}
            {text !== '' ? <span className="truncate">{text}</span> : null}
        </span>
    );
}
