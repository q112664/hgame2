import {
    overlayChipGroupClassName,
    overlayChipGroupDividerClassName,
    overlayChipGroupItemClassName,
} from '@/components/site/resource-card-styles';
import { abbreviateLanguage } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';

type Props = {
    languages: string[];
};

/**
 * Fused language chip group for resource card thumbnails.
 * Multiple languages share one frosted pill with light dividers.
 */
export function ResourceOverlayLanguageGroup({ languages }: Props) {
    if (languages.length === 0) {
        return null;
    }

    return (
        <div
            className={overlayChipGroupClassName}
            role="group"
            aria-label={languages.join(', ')}
        >
            {languages.map((language, index) => (
                <span
                    key={language}
                    className={cn(
                        overlayChipGroupItemClassName,
                        index > 0 && overlayChipGroupDividerClassName,
                    )}
                >
                    {abbreviateLanguage(language)}
                </span>
            ))}
        </div>
    );
}
