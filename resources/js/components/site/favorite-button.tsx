import { Heart } from 'lucide-react';
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

const heartActiveClassName =
    'bg-favorite/12 text-favorite hover:bg-favorite/18 dark:border dark:border-favorite/30 dark:bg-favorite/15 dark:hover:bg-favorite/25';

const heartIdleClassName =
    'bg-muted text-muted-foreground hover:bg-favorite/10 hover:text-favorite dark:border dark:border-foreground/15 dark:bg-surface-raised dark:text-foreground dark:hover:border-favorite/25 dark:hover:bg-favorite/15 dark:hover:text-favorite';

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
                    variant="secondary"
                    size={size}
                    aria-label={label}
                    aria-pressed={isFavorited}
                    disabled={isToggling}
                    onClick={onToggle}
                    className={cn(
                        'border-0 shadow-none ring-0',
                        isFavorited ? heartActiveClassName : heartIdleClassName,
                        className,
                    )}
                >
                    <Heart
                        className={cn('size-5', isFavorited && 'fill-current')}
                    />
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
