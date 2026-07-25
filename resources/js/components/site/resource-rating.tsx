import { Star } from 'lucide-react';
import { useState } from 'react';
import {
    heroActionIdleClassName,
    heroRatingActiveClassName,
} from '@/components/site/resource-detail-styles';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const RATING_MAX = 10;
const STAR_METER_MAX = 5;

function formatAverage(average: number): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(average);
}

function formatCount(count: number): string {
    return new Intl.NumberFormat('en-US').format(count);
}

type StarMeterProps = {
    /** Score on the 0–10 scale; rendered as a 5-star meter. */
    value: number;
    className?: string;
    starClassName?: string;
};

/** Read-only star meter mapped from a 0–10 score onto 5 stars. */
function StarMeter({
    value,
    className,
    starClassName = 'size-3.5',
}: StarMeterProps) {
    const stars = (Math.min(RATING_MAX, Math.max(0, value)) / RATING_MAX) * STAR_METER_MAX;
    const fillPercent = `${(stars / STAR_METER_MAX) * 100}%`;

    return (
        <span
            className={cn('relative inline-flex shrink-0', className)}
            aria-hidden
        >
            <span className="inline-flex text-muted-foreground/35">
                {Array.from({ length: STAR_METER_MAX }, (_, index) => (
                    <Star
                        key={`empty-${index}`}
                        className={cn(starClassName, 'fill-current')}
                    />
                ))}
            </span>
            <span
                className="absolute inset-y-0 left-0 overflow-hidden text-warning"
                style={{ width: fillPercent }}
            >
                <span className="inline-flex w-max">
                    {Array.from({ length: STAR_METER_MAX }, (_, index) => (
                        <Star
                            key={`filled-${index}`}
                            className={cn(starClassName, 'fill-current')}
                        />
                    ))}
                </span>
            </span>
        </span>
    );
}

type ResourceRatingButtonProps = {
    average: number | null;
    count: number;
    userRating: number | null;
    open?: boolean;
    onOpen: () => void;
    className?: string;
};

/** Rating CTA for the hero row; opens the rating dialog. */
export function ResourceRatingButton({
    average,
    count,
    userRating,
    open = false,
    onOpen,
    className,
}: ResourceRatingButtonProps) {
    const hasRated = userRating !== null;
    const formattedAverage =
        average === null ? null : formatAverage(average);
    const formattedCount = formatCount(count);

    const label = hasRated
        ? `Your rating: ${userRating}/${RATING_MAX}. Open rating dialog.`
        : 'Rate this title';

    const tooltip =
        count > 0
            ? `${formattedAverage} (${formattedCount}) · ${hasRated ? `Yours: ${userRating}/${RATING_MAX}` : 'Rate'}`
            : hasRated
              ? `Your rating: ${userRating}/${RATING_MAX}`
              : 'Rate this title';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    aria-label={label}
                    aria-haspopup="dialog"
                    aria-expanded={open}
                    onClick={onOpen}
                    className={cn(
                        'px-3',
                        hasRated
                            ? heroRatingActiveClassName
                            : heroActionIdleClassName,
                        !hasRated &&
                            'hover:border-warning/30 hover:bg-warning/10 hover:text-warning dark:hover:border-warning/25 dark:hover:bg-warning/15 dark:hover:text-warning',
                        className,
                    )}
                >
                    <Star
                        data-icon="inline-start"
                        className={cn('size-5', hasRated && 'fill-current')}
                    />
                    Rate
                </Button>
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}

type ResourceRatingPickerProps = {
    value: number | null;
    onRate: (score: number) => void;
    disabled?: boolean;
    className?: string;
};

/**
 * Interactive 1–10 score scale for the rating dialog.
 * Hover/focus previews 1…N; click sets the score (no toggle-to-clear).
 */
export function ResourceRatingPicker({
    value,
    onRate,
    disabled = false,
    className,
}: ResourceRatingPickerProps) {
    const [hovered, setHovered] = useState<number | null>(null);
    const active = hovered ?? value ?? 0;

    return (
        <div className={cn('flex w-full flex-col gap-2', className)}>
            <div
                className="flex items-center justify-between text-xs text-muted-foreground"
                aria-live="polite"
            >
                <span>1</span>
                <span className="font-heading text-sm font-semibold tabular-nums text-foreground">
                    {active > 0 ? `${active}/${RATING_MAX}` : `Select 1–${RATING_MAX}`}
                </span>
                <span>{RATING_MAX}</span>
            </div>

            <div
                role="radiogroup"
                aria-label="Rate this resource out of 10"
                className="grid w-full grid-cols-5 gap-1 sm:grid-cols-10"
                onMouseLeave={() => setHovered(null)}
            >
                {Array.from({ length: RATING_MAX }, (_, index) => {
                    const score = index + 1;
                    const filled = score <= active;
                    const selected = value === score;
                    const label = `Rate ${score} out of ${RATING_MAX}`;

                    return (
                        <button
                            key={score}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            aria-label={label}
                            disabled={disabled}
                            onMouseEnter={() => setHovered(score)}
                            onFocus={() => setHovered(score)}
                            onBlur={() => setHovered(null)}
                            onClick={() => onRate(score)}
                            className={cn(
                                'inline-flex h-9 items-center justify-center rounded-md',
                                'border text-sm font-medium tabular-nums transition-colors',
                                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                'disabled:pointer-events-none disabled:opacity-50',
                                filled
                                    ? 'border-warning/40 bg-warning/15 text-warning dark:border-warning/40 dark:bg-warning/20'
                                    : 'border-border bg-muted text-muted-foreground dark:border-foreground/15 dark:bg-surface-raised',
                                selected && 'ring-1 ring-warning/40',
                            )}
                        >
                            {score}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

type ResourceRatingSummaryProps = {
    average: number | null;
    count: number;
    className?: string;
};

/**
 * Single-line rating summary: average / 10 · star meter · count.
 * Empty and rated states share the same three slots for a stable layout.
 */
export function ResourceRatingSummary({
    average,
    count,
    className,
}: ResourceRatingSummaryProps) {
    const hasRatings = count > 0 && average !== null;
    const formattedAverage = hasRatings ? formatAverage(average) : '0.0';
    const formattedCount = formatCount(count);
    const countLabel = `${formattedCount} ${count === 1 ? 'rating' : 'ratings'}`;
    const ratingLabel = hasRatings
        ? `${formattedAverage} / ${RATING_MAX} average from ${countLabel}`
        : 'No ratings yet';

    return (
        <div
            role="group"
            className={cn(
                'inline-flex items-center gap-2 text-sm leading-none',
                className,
            )}
            aria-label={ratingLabel}
        >
            <span
                className={cn(
                    'inline-flex items-baseline gap-0.5 font-heading font-semibold tracking-tight',
                    hasRatings
                        ? 'text-foreground'
                        : 'text-muted-foreground',
                )}
            >
                <span className="text-xl tabular-nums">{formattedAverage}</span>
                <span className="text-xs font-medium text-muted-foreground">
                    /{RATING_MAX}
                </span>
            </span>
            <StarMeter
                value={hasRatings ? average : 0}
                starClassName="size-3.5"
            />
            <span
                className={cn(
                    hasRatings
                        ? 'text-muted-foreground'
                        : 'text-muted-foreground/80',
                )}
            >
                {countLabel}
            </span>
        </div>
    );
}

type ResourceRatingDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    average: number | null;
    count: number;
    userRating: number | null;
    isSaving: boolean;
    onRate: (score: number) => void;
    onClear: () => void;
};

/** Modal for rating a resource without crowding the hero CTA row. */
export function ResourceRatingDialog({
    open,
    onOpenChange,
    title,
    average,
    count,
    userRating,
    isSaving,
    onRate,
    onClear,
}: ResourceRatingDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md gap-5 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {userRating !== null ? 'Your rating' : 'Rate this title'}
                    </DialogTitle>
                    <DialogDescription className="line-clamp-2">
                        {title}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center gap-4 py-1">
                    <ResourceRatingSummary
                        average={average}
                        count={count}
                    />

                    <ResourceRatingPicker
                        value={userRating}
                        onRate={onRate}
                        disabled={isSaving}
                    />

                    <div className="flex w-full items-center justify-center gap-3">
                        <p className="text-center text-xs text-muted-foreground">
                            {userRating !== null
                                ? `You rated ${userRating}/${RATING_MAX}`
                                : `Hover to preview · click to set 1–${RATING_MAX}`}
                        </p>
                        {userRating !== null ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="xs"
                                disabled={isSaving}
                                onClick={onClear}
                                className="text-muted-foreground"
                            >
                                Clear
                            </Button>
                        ) : null}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
