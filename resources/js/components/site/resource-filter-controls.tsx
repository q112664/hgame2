import { router } from '@inertiajs/react';
import { ArrowUpDown, ChevronDown, Search, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

const SORT_OPTIONS: Array<{ value: SortOption; label: string }> = [
    { value: 'latest', label: 'Latest' },
    { value: 'oldest', label: 'Oldest' },
    { value: 'title', label: 'Title A–Z' },
    { value: 'views', label: 'Most viewed' },
];

export const DEFAULT_FILTERS: ResourceFilters = {
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

    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-9 w-full justify-between border-foreground/10 bg-card px-3 font-normal shadow-none"
                    >
                        <span className="truncate">{selectedLabel}</span>
                        <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
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
        </div>
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

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="h-9 gap-2 border-foreground/10 bg-card px-3 font-normal shadow-none"
                >
                    <ArrowUpDown className="size-4 text-muted-foreground" />
                    <span className="truncate">{selectedLabel}</span>
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
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
                    className="h-9 justify-start gap-2 border-foreground/10 bg-card shadow-none"
                >
                    <Tags className="size-4 text-muted-foreground" />
                    <span>Tags</span>
                    {selected.length > 0 ? (
                        <Badge
                            variant="secondary"
                            className="ml-auto h-5 rounded-md px-1.5 text-[11px]"
                        >
                            {selected.length}
                        </Badge>
                    ) : null}
                </Button>
            </DialogTrigger>
            <DialogContent className="gap-4 sm:max-w-lg">
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
                        className="h-10 border-foreground/10 bg-background pl-9 shadow-none"
                        autoFocus
                    />
                </div>

                <div className="max-h-72 overflow-y-auto rounded-lg ring-1 ring-foreground/10">
                    {filtered.length === 0 ? (
                        <p className="px-3 py-8 text-center text-sm text-muted-foreground">
                            No tags found
                        </p>
                    ) : (
                        <ul className="divide-y divide-foreground/5 p-1">
                            {filtered.map((tag) => {
                                const checked = draft.includes(tag.slug);
                                const id = `tag-filter-${tag.slug}`;

                                return (
                                    <li key={tag.slug}>
                                        <label
                                            htmlFor={id}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-md px-2.5 py-2',
                                                'hover:bg-muted/60',
                                            )}
                                        >
                                            <Checkbox
                                                id={id}
                                                checked={checked}
                                                onCheckedChange={() =>
                                                    toggleTag(tag.slug)
                                                }
                                            />
                                            <span className="text-sm text-foreground">
                                                {tag.name}
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
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
