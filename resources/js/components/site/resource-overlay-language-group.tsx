import { overlayChipClassName } from '@/components/site/resource-card-styles';
import { abbreviateLanguage } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';

type Props = {
    languages: string[];
};

/**
 * Separate language chips on resource card thumbnails (same style as platform badges).
 */
export function ResourceOverlayLanguageGroup({ languages }: Props) {
    if (languages.length === 0) {
        return null;
    }

    return (
        <div
            className="flex flex-wrap justify-end gap-1"
            role="group"
            aria-label={languages.join(', ')}
        >
            {languages.map((language) => (
                <span
                    key={language}
                    className={cn(overlayChipClassName)}
                    title={language}
                >
                    {abbreviateLanguage(language)}
                </span>
            ))}
        </div>
    );
}
