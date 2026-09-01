import { Link } from '@inertiajs/react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { MutableRefObject, RefObject } from 'react';
import { cn } from '@/lib/utils';

export type RouteTab<Value extends string> = {
    value: Value;
    label: string;
    href: string;
};

type Props<Value extends string> = {
    tabs: readonly RouteTab<Value>[];
    activeValue: Value;
    displayedValue?: Value;
    ariaLabel?: string;
    className?: string;
    listRef?: RefObject<HTMLElement | null>;
    tabRefs?: MutableRefObject<Partial<Record<Value, HTMLElement | null>>>;
    onClick?: (event: React.MouseEvent, value: Value) => void;
    /** Client-side tab switching: skip Inertia visits and call this instead. */
    onSelect?: (value: Value) => void;
    onStart?: (value: Value, navigationId: number) => void;
    onSuccess?: (value: Value, navigationId: number | null) => void;
    onError?: (value: Value, navigationId: number | null) => void;
    onCancel?: (value: Value, navigationId: number | null) => void;
};

/**
 * Route-backed section tabs: horizontal scroll + underline indicator.
 * Scales past three items without a fixed grid.
 */
export function RouteTabs<Value extends string>({
    tabs,
    activeValue,
    displayedValue = activeValue,
    ariaLabel = 'Sections',
    className,
    listRef,
    tabRefs: externalTabRefs,
    onClick,
    onSelect,
    onStart,
    onSuccess,
    onError,
    onCancel,
}: Props<Value>) {
    const tabsListRef = useRef<HTMLElement | null>(null);
    const tabRefs = useRef<Partial<Record<Value, HTMLElement | null>>>({});
    const navigationSequence = useRef(0);
    const navigationIds = useRef(new Map<Value, number>());
    const [optimisticValue, setOptimisticValue] = useState<Value | null>(null);
    const effectiveDisplayedValue = optimisticValue ?? displayedValue;

    useLayoutEffect(() => {
        const activeTrigger = tabRefs.current[effectiveDisplayedValue];

        if (!activeTrigger) {
            return;
        }

        activeTrigger.scrollIntoView({
            inline: 'nearest',
            block: 'nearest',
            behavior: 'smooth',
        });
    }, [effectiveDisplayedValue]);

    const navigationIdFor = (value: Value): number | null =>
        navigationIds.current.get(value) ?? null;

    return (
        <nav
            ref={(node) => {
                tabsListRef.current = node;

                if (listRef) {
                    listRef.current = node;
                }
            }}
            aria-label={ariaLabel}
            className={cn(
                'w-full scroll-mt-20 border-b border-border',
                className,
            )}
        >
            <div
                className={cn(
                    'flex w-full items-stretch gap-0 overflow-x-auto overscroll-x-contain',
                    '[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden',
                )}
            >
                {tabs.map((tab) => {
                    const isActive = effectiveDisplayedValue === tab.value;
                    const className = cn(
                        'relative inline-flex shrink-0 items-center justify-center border-b-2 px-3.5 py-2.5 text-sm font-medium whitespace-nowrap',
                        'transition-[color,border-color] duration-200',
                        'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                        'motion-reduce:transition-none',
                        isActive
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground/85',
                    );
                    const assignRef = (node: HTMLElement | null) => {
                        tabRefs.current[tab.value] = node;

                        if (externalTabRefs) {
                            externalTabRefs.current[tab.value] = node;
                        }
                    };

                    if (onSelect) {
                        return (
                            <a
                                key={tab.value}
                                ref={assignRef}
                                href={tab.href}
                                aria-current={
                                    activeValue === tab.value
                                        ? 'page'
                                        : undefined
                                }
                                className={className}
                                onClick={(event) => {
                                    onClick?.(event, tab.value);

                                    if (
                                        event.defaultPrevented ||
                                        event.button !== 0 ||
                                        event.metaKey ||
                                        event.ctrlKey ||
                                        event.shiftKey ||
                                        event.altKey
                                    ) {
                                        return;
                                    }

                                    event.preventDefault();

                                    if (activeValue !== tab.value) {
                                        onSelect(tab.value);
                                    }
                                }}
                            >
                                {tab.label}
                            </a>
                        );
                    }

                    return (
                        <Link
                            key={tab.value}
                            ref={(node) => {
                                assignRef(node as HTMLElement | null);
                            }}
                            href={tab.href}
                            aria-current={
                                activeValue === tab.value ? 'page' : undefined
                            }
                            className={className}
                            headers={{ 'X-Resource-Tab-Nav': '1' }}
                            preserveState
                            preserveScroll
                            onClick={(event) => {
                                if (activeValue === tab.value) {
                                    event.preventDefault();
                                }

                                onClick?.(event, tab.value);
                            }}
                            onStart={() => {
                                const navigationId =
                                    navigationSequence.current + 1;
                                navigationSequence.current = navigationId;
                                navigationIds.current.set(
                                    tab.value,
                                    navigationId,
                                );
                                setOptimisticValue(tab.value);
                                onStart?.(tab.value, navigationId);
                            }}
                            onSuccess={() => {
                                setOptimisticValue(null);
                                onSuccess?.(
                                    tab.value,
                                    navigationIdFor(tab.value),
                                );
                            }}
                            onError={() => {
                                setOptimisticValue(null);
                                onError?.(
                                    tab.value,
                                    navigationIdFor(tab.value),
                                );
                            }}
                            onCancel={() => {
                                setOptimisticValue(null);
                                onCancel?.(
                                    tab.value,
                                    navigationIdFor(tab.value),
                                );
                            }}
                        >
                            {tab.label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
