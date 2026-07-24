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
    onStart?: (value: Value, navigationId: number) => void;
    onSuccess?: (value: Value, navigationId: number | null) => void;
    onError?: (value: Value, navigationId: number | null) => void;
    onCancel?: (value: Value, navigationId: number | null) => void;
};

export function RouteTabs<Value extends string>({
    tabs,
    activeValue,
    displayedValue = activeValue,
    ariaLabel = 'Sections',
    className,
    listRef,
    tabRefs: externalTabRefs,
    onClick,
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
    const [pill, setPill] = useState({ left: 0, width: 0, ready: false });
    const effectiveDisplayedValue = optimisticValue ?? displayedValue;

    useLayoutEffect(() => {
        const updatePill = () => {
            const list = tabsListRef.current;
            const activeTrigger = tabRefs.current[effectiveDisplayedValue];

            if (!list || !activeTrigger) {
                return;
            }

            const listRect = list.getBoundingClientRect();
            const triggerRect = activeTrigger.getBoundingClientRect();

            setPill({
                left: triggerRect.left - listRect.left,
                width: triggerRect.width,
                ready: true,
            });
        };

        updatePill();
        window.addEventListener('resize', updatePill);

        return () => window.removeEventListener('resize', updatePill);
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
                'relative grid h-auto w-full scroll-mt-20 grid-cols-3 gap-0.5 rounded-md border border-border bg-card p-1 sm:inline-grid sm:w-auto',
                className,
            )}
        >
            {pill.ready ? (
                <span
                    aria-hidden
                    className="absolute top-1 bottom-1 rounded-sm bg-muted"
                    style={{ left: pill.left, width: pill.width }}
                />
            ) : null}

            {tabs.map((tab) => (
                <Link
                    key={tab.value}
                    ref={(node) => {
                        tabRefs.current[tab.value] = node as HTMLElement | null;

                        if (externalTabRefs) {
                            externalTabRefs.current[tab.value] =
                                node as HTMLElement | null;
                        }
                    }}
                    href={tab.href}
                    aria-current={
                        activeValue === tab.value ? 'page' : undefined
                    }
                    className={cn(
                        'relative z-10 inline-flex h-9 items-center justify-center rounded-md border-transparent px-4 text-sm font-medium shadow-none',
                        'text-muted-foreground',
                        'hover:bg-transparent hover:text-foreground/80',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        effectiveDisplayedValue === tab.value
                            ? 'text-foreground'
                            : 'text-muted-foreground',
                    )}
                    preserveState
                    preserveScroll
                    onClick={(event) => {
                        if (activeValue === tab.value) {
                            event.preventDefault();
                        }

                        onClick?.(event, tab.value);
                    }}
                    onStart={() => {
                        const navigationId = navigationSequence.current + 1;
                        navigationSequence.current = navigationId;
                        navigationIds.current.set(tab.value, navigationId);
                        setOptimisticValue(tab.value);
                        onStart?.(tab.value, navigationId);
                    }}
                    onSuccess={() => {
                        setOptimisticValue(null);
                        onSuccess?.(tab.value, navigationIdFor(tab.value));
                    }}
                    onError={() => {
                        setOptimisticValue(null);
                        onError?.(tab.value, navigationIdFor(tab.value));
                    }}
                    onCancel={() => {
                        setOptimisticValue(null);
                        onCancel?.(tab.value, navigationIdFor(tab.value));
                    }}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
