import { Star } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

type ResourceRatingStarsProps = {
    value: number | null;
    onRate: (score: number) => void;
    disabled?: boolean;
    className?: string;
};

/**
 * Interactive 1–5 star picker for the resource hero.
 * Clicking the active score again clears the rating (handled by the hook).
 */
export function ResourceRatingStars({
    value,
    onRate,
    disabled = false,
    className,
}: ResourceRatingStarsProps) {
    const [hovered, setHovered] = useState<number | null>(null);
    const active = hovered ?? value ?? 0;

    return (
        <div
            role="radiogroup"
            aria-label="Rate this resource"
            className={cn(
                'inline-flex h-10 items-center rounded-md bg-muted px-1.5',
                'dark:border dark:border-foreground/15 dark:bg-surface-raised',
                className,
            )}
            onMouseLeave={() => setHovered(null)}
        >
            {[1, 2, 3, 4, 5].map((score) => {
                const filled = score <= active;
                const label =
                    value === score
                        ? `Clear ${score}-star rating`
                        : `Rate ${score} out of 5`;

                return (
                    <button
                        key={score}
                        type="button"
                        role="radio"
                        aria-checked={value === score}
                        aria-label={label}
                        disabled={disabled}
                        onMouseEnter={() => setHovered(score)}
                        onFocus={() => setHovered(score)}
                        onBlur={() => setHovered(null)}
                        onClick={() => onRate(score)}
                        className={cn(
                            'inline-flex size-7 items-center justify-center rounded-sm',
                            'text-muted-foreground transition-colors',
                            'hover:text-warning focus-visible:text-warning',
                            'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                            'disabled:pointer-events-none disabled:opacity-50',
                            filled && 'text-warning',
                        )}
                    >
                        <Star
                            className={cn(
                                'size-4',
                                filled && 'fill-current',
                            )}
                            aria-hidden
                        />
                    </button>
                );
            })}
        </div>
    );
}

type ResourceRatingSummaryProps = {
    average: number | null;
    count: number;
    className?: string;
};

/** Compact average + count for the resource hero meta row. */
export function ResourceRatingSummary({
    average,
    count,
    className,
}: ResourceRatingSummaryProps) {
    const formattedAverage =
        average === null
            ? null
            : new Intl.NumberFormat('en-US', {
                  minimumFractionDigits: 1,
                  maximumFractionDigits: 1,
              }).format(average);

    const formattedCount = new Intl.NumberFormat('en-US').format(count);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5',
                className,
            )}
            title={
                count > 0
                    ? `${formattedAverage} average from ${formattedCount} ratings`
                    : 'No ratings yet'
            }
        >
            <Star
                className={cn(
                    'size-3.5 shrink-0',
                    average !== null
                        ? 'fill-warning text-warning'
                        : 'text-muted-foreground',
                )}
                aria-hidden
            />
            {average !== null ? (
                <>
                    <span className="tabular-nums text-foreground">
                        {formattedAverage}
                    </span>
                    <span className="text-muted-foreground">
                        ({formattedCount})
                    </span>
                </>
            ) : (
                <span>No ratings</span>
            )}
        </span>
    );
}
