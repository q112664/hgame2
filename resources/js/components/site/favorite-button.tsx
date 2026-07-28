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
    /** Inherits sizing; pass `size="icon-lg"` to match the detail page. */
    size?: 'icon' | 'icon-lg';
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
    className,
}: FavoriteButtonProps) {
    const label = isFavorited ? 'Remove from favorites' : 'Add to favorites';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size={size}
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
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
