import { ThumbsUp } from 'lucide-react';
import {
    likeButtonActiveClassName,
    likeButtonClassName,
} from '@/components/site/resource-detail-styles';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatViews } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';

type LikeButtonProps = {
    isLiked: boolean;
    likesCount: number;
    isToggling: boolean;
    onToggle: () => void;
    className?: string;
};

/**
 * Thumbs-up like toggle for resource download footers.
 * Pairs with the `useLike` hook for optimistic state.
 */
export function LikeButton({
    isLiked,
    likesCount,
    isToggling,
    onToggle,
    className,
}: LikeButtonProps) {
    const label = isLiked ? 'Unlike' : 'Like';
    const countLabel = likesCount > 0 ? formatViews(likesCount) : undefined;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label={
                        likesCount > 0 ? `${label} (${likesCount})` : label
                    }
                    aria-pressed={isLiked}
                    disabled={isToggling}
                    onClick={onToggle}
                    className={cn(
                        isLiked
                            ? likeButtonActiveClassName
                            : likeButtonClassName,
                        className,
                    )}
                >
                    <ThumbsUp
                        className={cn('size-4', isLiked && 'fill-current')}
                        strokeWidth={2.25}
                    />
                    {countLabel ? (
                        <span className="tabular-nums">{countLabel}</span>
                    ) : null}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
