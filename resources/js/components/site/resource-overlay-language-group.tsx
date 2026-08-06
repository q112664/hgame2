import { overlayChipClassName } from '@/components/site/resource-card-styles';
import { abbreviateLanguage } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import type { GameLanguage } from '@/types/resources';

type Props = {
    languages: GameLanguage[];
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
            aria-label={languages.map((language) => language.name).join(', ')}
        >
            {languages.map((language) => (
                <span
                    key={language.code}
                    className={cn(overlayChipClassName)}
                    title={language.name}
                >
                    {abbreviateLanguage(language.name)}
                </span>
            ))}
        </div>
    );
}
