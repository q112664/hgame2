import { Heart } from 'lucide-react';
import {
    heroActionIdleClassName,
    heroFavoriteActiveClassName,
} from '@/components/site/resource-detail-styles';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type FavoriteButtonProps = {
    isFavorited: boolean;
    isToggling: boolean;
    onToggle: () => void;
    /** Controls the icon-only size; labeled buttons use the default size. */
    size?: 'icon' | 'icon-lg';
    showLabel?: boolean;
    className?: string;
};

/**
 * Heart-shaped favorite toggle used on resource pages.
 * Pairs with the `useFavorite` hook to drive an optimistic toggle.
 */
export function FavoriteButton({
    isFavorited,
    isToggling,
    onToggle,
    size = 'icon-lg',
    showLabel = false,
    className,
}: FavoriteButtonProps) {
    const label = isFavorited ? 'Remove from favorites' : 'Add to favorites';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size={showLabel ? 'default' : size}
                    aria-label={label}
                    aria-pressed={isFavorited}
                    disabled={isToggling}
                    onClick={onToggle}
                    className={cn(
                        isFavorited
                            ? heroFavoriteActiveClassName
                            : heroActionIdleClassName,
                        !isFavorited &&
                            'hover:bg-favorite/10 hover:text-favorite dark:hover:bg-favorite/20 dark:hover:text-favorite',
                        showLabel && 'h-9 gap-1.5 px-3.5',
                        'disabled:opacity-60 dark:disabled:opacity-50',
                        className,
                    )}
                >
                    <Heart
                        className={cn(
                            'size-5',
                            isFavorited && 'fill-current',
                            !isFavorited && 'dark:opacity-90',
                        )}
                    />
                    {showLabel ? 'Favorite' : null}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
