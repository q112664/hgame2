import { router } from '@inertiajs/react';
import { ArrowUpDown, ChevronDown, Search, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index as resourcesIndex } from '@/routes/resources';

export type FilterOption = {
    name: string;
    slug: string;
};

export type LanguageOption = {
    name: string;
    code: string;
};

export type SortOption = 'latest' | 'oldest' | 'title' | 'views';

export type ResourceFilters = {
    q: string;
    category: string | null;
    platform: string | null;
    language: string | null;
    tags: string[];
    sort: SortOption;
};

export type FilterOptions = {
    categories: FilterOption[];
    platforms: FilterOption[];
    languages: LanguageOption[];
    tags: FilterOption[];
};

const ALL_VALUE = '__all__';

/** Shared inset field surface for filter controls inside the filter panel. */
export const filterControlClassName = cn(
    'h-9 border-border/70 bg-muted/45 font-normal text-foreground shadow-none',
    'hover:border-border hover:bg-muted/70',
    'dark:border-foreground/12 dark:bg-surface-raised',
    'dark:hover:border-foreground/20 dark:hover:bg-surface-strong',
);

/** Slightly inked surface when a filter has an active value. */
export const filterControlActiveClassName = cn(
    'border-foreground/18 bg-foreground/[0.05]',
    'hover:border-foreground/25 hover:bg-foreground/[0.08]',
    'dark:border-foreground/25 dark:bg-foreground/10',
    'dark:hover:border-foreground/35 dark:hover:bg-foreground/14',
);

const SORT_OPTIONS: Array<{ value: SortOption; label: string }> = [
    { value: 'latest', label: 'Latest' },
    { value: 'oldest', label: 'Oldest' },
    { value: 'title', label: 'Title A–Z' },
    { value: 'views', label: 'Most viewed' },
];

export const DEFAULT_FILTERS: ResourceFilters = {
    q: '',
    category: null,
    platform: null,
    language: null,
    tags: [],
    sort: 'latest',
};

export function FilterMenu({
    label,
    value,
    allLabel,
    options,
    onChange,
}: {
    label: string;
    value: string | null;
    allLabel: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string | null) => void;
}) {
    const selectedLabel =
        value === null
            ? allLabel
            : (options.find((option) => option.value === value)?.label ??
              allLabel);
    const isActive = value !== null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-label={label}
                    className={cn(
                        'w-full justify-between px-3',
                        filterControlClassName,
                        isActive && filterControlActiveClassName,
                    )}
                >
                    <span
                        className={cn(
                            'truncate',
                            isActive
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {selectedLabel}
                    </span>
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground/80" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="max-h-72 w-(--radix-dropdown-menu-trigger-width)"
            >
                <DropdownMenuRadioGroup
                    value={value ?? ALL_VALUE}
                    onValueChange={(next) =>
                        onChange(next === ALL_VALUE ? null : next)
                    }
                >
                    <DropdownMenuRadioItem value={ALL_VALUE}>
                        {allLabel}
                    </DropdownMenuRadioItem>
                    {options.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.value}
                            value={option.value}
                        >
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function SortMenu({
    value,
    onChange,
}: {
    value: SortOption;
    onChange: (value: SortOption) => void;
}) {
    const selectedLabel =
        SORT_OPTIONS.find((option) => option.value === value)?.label ??
        'Latest';
    const isActive = value !== 'latest';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'gap-2 px-3',
                        filterControlClassName,
                        isActive && filterControlActiveClassName,
                    )}
                >
                    <ArrowUpDown className="size-4 text-muted-foreground/80" />
                    <span
                        className={cn(
                            'truncate',
                            isActive
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {selectedLabel}
                    </span>
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground/80" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-44">
                <DropdownMenuRadioGroup
                    value={value}
                    onValueChange={(next) => onChange(next as SortOption)}
                >
                    {SORT_OPTIONS.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.value}
                            value={option.value}
                        >
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function filterQuery(
    filters: ResourceFilters,
    page?: number,
): Record<string, string | number | string[]> {
    const query: Record<string, string | number | string[]> = {};

    if (filters.q.trim() !== '') {
        query.q = filters.q.trim();
    }

    if (filters.category) {
        query.category = filters.category;
    }

    if (filters.platform) {
        query.platform = filters.platform;
    }

    if (filters.language) {
        query.language = filters.language;
    }

    if (filters.tags.length > 0) {
        query.tags = filters.tags;
    }

    if (filters.sort !== 'latest') {
        query.sort = filters.sort;
    }

    if (page && page > 1) {
        query.page = page;
    }

    return query;
}

export function visitFilters(
    next: ResourceFilters,
    options: { page?: number; onFinish?: () => void } = {},
) {
    router.get(
        resourcesIndex.url({
            query: filterQuery(next, options.page),
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['resources', 'filters'],
            onFinish: options.onFinish,
        },
    );
}

export function TagFilterDialog({
    options,
    selected,
    onApply,
}: {
    options: FilterOption[];
    selected: string[];
    onApply: (tags: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [draft, setDraft] = useState<string[]>(selected);

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return options;
        }

        return options.filter((tag) => tag.name.toLowerCase().includes(term));
    }, [options, query]);

    const openDialog = (nextOpen: boolean) => {
        if (nextOpen) {
            setDraft(selected);
            setQuery('');
        }

        setOpen(nextOpen);
    };

    const toggleTag = (slug: string) => {
        setDraft((current) =>
            current.includes(slug)
                ? current.filter((tag) => tag !== slug)
                : [...current, slug],
        );
    };

    return (
        <Dialog open={open} onOpenChange={openDialog}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'justify-start gap-2',
                        filterControlClassName,
                        selected.length > 0 && filterControlActiveClassName,
                    )}
                >
                    <Tags className="size-4 text-muted-foreground/80" />
                    <span
                        className={cn(
                            selected.length > 0
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        Tags
                    </span>
                    {selected.length > 0 ? (
                        <Badge
                            variant="secondary"
                            className="ml-auto h-5 rounded-sm border-0 bg-foreground/10 px-1.5 text-[11px] text-foreground"
                        >
                            {selected.length}
                        </Badge>
                    ) : null}
                </Button>
            </DialogTrigger>
            <DialogContent className="gap-4 sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Filter by tags</DialogTitle>
                    <DialogDescription>
                        Search and select tags. Resources must match all
                        selected tags.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search tags…"
                        className="h-10 border-border bg-background pl-9 shadow-none"
                        autoFocus
                    />
                </div>

                <div className="max-h-80 overflow-y-auto rounded-md border border-border bg-muted/30 p-3">
                    {filtered.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No tags found
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {filtered.map((tag) => {
                                const checked = draft.includes(tag.slug);

                                return (
                                    <button
                                        key={tag.slug}
                                        type="button"
                                        aria-pressed={checked}
                                        onClick={() => toggleTag(tag.slug)}
                                        className={cn(
                                            'inline-flex h-7 max-w-full items-center rounded-sm px-2.5 text-xs font-medium transition-colors',
                                            'ring-1 ring-inset focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                            checked
                                                ? 'bg-foreground text-background ring-foreground'
                                                : 'bg-card text-muted-foreground ring-border hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        <span className="truncate">
                                            {tag.name}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        className="shadow-none"
                        onClick={() => setDraft([])}
                        disabled={draft.length === 0}
                    >
                        Clear
                    </Button>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="shadow-none"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                onApply(draft);
                                setOpen(false);
                            }}
                        >
                            Apply
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
