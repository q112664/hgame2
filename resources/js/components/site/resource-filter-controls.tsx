import { router } from '@inertiajs/react';
import {
    ArrowUpDown,
    ArrowDownWideNarrow,
    ArrowUpNarrowWide,
    ChevronDown,
    Search,
    Tags,
} from 'lucide-react';
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
import {
    genre as resourcesGenre,
    index as resourcesIndex,
    language as resourcesLanguage,
    platform as resourcesPlatform,
    tag as resourcesTag,
} from '@/routes/resources';

export type FilterOption = {
    name: string;
    slug: string;
};

export type LanguageOption = {
    name: string;
    code: string;
};

/** Orderings only — filters are what change the result set. */
export type SortOption = 'latest' | 'oldest' | 'updated' | 'title' | 'views';

/** Sort fields offered in the UI. `title` is reachable from legacy links only. */
export type SortField = 'published' | 'updated' | 'views' | 'title';

export type SortDirection = 'asc' | 'desc';

export type ResourceFilters = {
    q: string;
    category: string | null;
    platform: string | null;
    language: string | null;
    tags: string[];
    sort: SortOption;
    dir: SortDirection;
};

export type FilterOptions = {
    categories: FilterOption[];
    platforms: FilterOption[];
    languages: LanguageOption[];
    tags: FilterOption[];
};

/** Shared inset field surface for filter controls inside the filter panel. */
export const filterControlClassName = cn(
    'h-9 border-border/70 bg-muted/45 font-normal text-foreground shadow-none',
    'hover:border-border hover:bg-muted/70',
    'focus-visible:border-border/70 focus-visible:ring-0',
    'dark:border-foreground/12 dark:bg-surface-raised',
    'dark:hover:border-foreground/20 dark:hover:bg-surface-strong',
    'dark:focus-visible:border-foreground/12',
);

/** Soft primary tint when a filter has an active value. */
export const filterControlActiveClassName = cn(
    'border-primary/25 bg-primary/8',
    'hover:border-primary/35 hover:bg-primary/12',
    'focus-visible:border-primary/25',
    'dark:border-primary/30 dark:bg-primary/12',
    'dark:hover:border-primary/40 dark:hover:bg-primary/16',
    'dark:focus-visible:border-primary/30',
);

const SORT_FIELDS: Array<{ value: SortField; label: string }> = [
    { value: 'published', label: 'Published date' },
    { value: 'updated', label: 'Last updated' },
    { value: 'views', label: 'Views' },
];

/** Legacy ordering from old links; shown only while it is the active sort. */
const LEGACY_TITLE_FIELD: { value: SortField; label: string } = {
    value: 'title',
    label: 'Title A–Z',
};

export const DEFAULT_FILTERS: ResourceFilters = {
    q: '',
    category: null,
    platform: null,
    language: null,
    tags: [],
    sort: 'latest',
    dir: 'desc',
};

/** Sort field behind a canonical sort value. */
export function sortFieldOf(sort: SortOption): SortField {
    if (sort === 'updated' || sort === 'views' || sort === 'title') {
        return sort;
    }

    return 'published';
}

/** Direction a sort value uses when the URL omits `dir`. */
export function defaultDirectionForSort(sort: SortOption): SortDirection {
    return sort === 'oldest' || sort === 'title' ? 'asc' : 'desc';
}

/** Direction a field starts on, so the toggle only highlights real changes. */
export function defaultDirectionForField(field: SortField): SortDirection {
    return field === 'title' ? 'asc' : 'desc';
}

/**
 * Canonical ordering for a field + direction pair, so equivalent URLs
 * (`published` descending and `latest`) collapse into one URL shape.
 */
export function sortSelection(
    field: SortField,
    direction: SortDirection,
): { sort: SortOption; dir: SortDirection } {
    if (field === 'published') {
        return direction === 'asc'
            ? { sort: 'oldest', dir: 'asc' }
            : { sort: 'latest', dir: 'desc' };
    }

    return { sort: field, dir: direction };
}

/** Dimensions that narrow the result set (sort and search excluded). */
export function countActiveFilters(filters: ResourceFilters): number {
    return (
        (filters.category ? 1 : 0) +
        (filters.platform ? 1 : 0) +
        (filters.language ? 1 : 0) +
        filters.tags.length
    );
}

export function SortFieldMenu({
    value,
    onChange,
}: {
    value: SortField;
    onChange: (field: SortField) => void;
}) {
    const options =
        value === 'title' ? [LEGACY_TITLE_FIELD, ...SORT_FIELDS] : SORT_FIELDS;
    const selectedLabel =
        options.find((option) => option.value === value)?.label ??
        'Published date';
    const isActive = value !== 'published';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-label="Sort resources"
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
                    onValueChange={(next) => onChange(next as SortField)}
                >
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

/**
 * Direction toggle; it sits next to the field menu because they are one order.
 * The label is abbreviated so the control stays compact next to the field menu.
 */
export function SortDirectionButton({
    field,
    value,
    onChange,
}: {
    field: SortField;
    value: SortDirection;
    onChange: (direction: SortDirection) => void;
}) {
    const ascending = value === 'asc';
    const label = ascending ? 'Ascending' : 'Descending';
    const Icon = ascending ? ArrowUpNarrowWide : ArrowDownWideNarrow;
    const isActive = value !== defaultDirectionForField(field);

    return (
        <Button
            type="button"
            variant="outline"
            aria-label={`Sort direction: ${label.toLowerCase()}`}
            title={label}
            className={cn(
                'gap-2 px-3',
                filterControlClassName,
                isActive && filterControlActiveClassName,
                'focus-visible:border-border/70 focus-visible:ring-0 focus-visible:outline-none',
                'dark:focus-visible:border-foreground/12',
            )}
            onClick={() => onChange(ascending ? 'desc' : 'asc')}
        >
            <Icon className="size-4 text-muted-foreground/80" />
            <span
                className={cn(
                    isActive ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {ascending ? 'Asc' : 'Desc'}
            </span>
        </Button>
    );
}

const ALL_VALUE = '__all__';

/** Single-select dimension as a dropdown menu; picking “All” clears it. */
export function FilterMenu({
    label,
    allLabel,
    value,
    options,
    onChange,
}: {
    label: string;
    allLabel: string;
    value: string | null;
    options: FilterOption[];
    onChange: (value: string | null) => void;
}) {
    if (options.length === 0) {
        return null;
    }

    const selectedLabel =
        value === null
            ? allLabel
            : (options.find((option) => option.slug === value)?.name ??
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
                            key={option.slug}
                            value={option.slug}
                        >
                            {option.name}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Tag filter as a modal: the taxonomy is long, and the draft is only applied on
 * “Apply”, so the trigger stays the same size as the other filter controls.
 */
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
                    aria-label="Tags"
                    className={cn(
                        'w-full justify-start gap-2 px-3',
                        filterControlClassName,
                        selected.length > 0 && filterControlActiveClassName,
                    )}
                >
                    <Tags className="size-4 shrink-0 text-muted-foreground/80" />
                    <span
                        className={cn(
                            'truncate',
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
                        placeholder="Search tags"
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
                                                ? 'bg-primary text-primary-foreground ring-primary'
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

export function filterQuery(
    filters: ResourceFilters,
    page?: number,
    options: { omitTaxonomyKeys?: boolean } = {},
): Record<string, string | number | string[]> {
    const query: Record<string, string | number | string[]> = {};
    const omitTaxonomy = options.omitTaxonomyKeys === true;

    if (filters.q.trim() !== '') {
        query.q = filters.q.trim();
    }

    if (!omitTaxonomy && filters.category) {
        query.category = filters.category;
    }

    if (!omitTaxonomy && filters.platform) {
        query.platform = filters.platform;
    }

    if (!omitTaxonomy && filters.language) {
        query.language = filters.language;
    }

    if (!omitTaxonomy && filters.tags.length > 0) {
        query.tags = filters.tags;
    }

    if (filters.sort !== 'latest') {
        query.sort = filters.sort;
    }

    if (filters.dir !== defaultDirectionForSort(filters.sort)) {
        query.dir = filters.dir;
    }

    if (page && page > 1) {
        query.page = page;
    }

    return query;
}

/**
 * Single pure taxonomy dimension → path URL; otherwise query-string catalog.
 * The update feed always stays on the query-string catalog.
 */
export function catalogUrl(filters: ResourceFilters, page?: number): string {
    const q = filters.q.trim();
    const hasCategory = Boolean(filters.category);
    const hasPlatform = Boolean(filters.platform);
    const hasLanguage = Boolean(filters.language);
    const tagCount = filters.tags.length;

    const dimensionCount =
        (hasCategory ? 1 : 0) +
        (hasPlatform ? 1 : 0) +
        (hasLanguage ? 1 : 0) +
        (tagCount > 0 ? 1 : 0);

    if (q === '' && dimensionCount === 1 && tagCount <= 1) {
        const query = filterQuery(filters, page, { omitTaxonomyKeys: true });

        if (hasCategory && filters.category) {
            return resourcesGenre.url(filters.category, { query });
        }

        if (hasPlatform && filters.platform) {
            return resourcesPlatform.url(filters.platform, { query });
        }

        if (hasLanguage && filters.language) {
            return resourcesLanguage.url(filters.language, { query });
        }

        if (tagCount === 1) {
            return resourcesTag.url(filters.tags[0]!, { query });
        }
    }

    return resourcesIndex.url({
        query: filterQuery(filters, page),
    });
}

export function visitFilters(
    next: ResourceFilters,
    options: { page?: number; onFinish?: () => void } = {},
) {
    router.get(
        catalogUrl(next, options.page),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: [
                'resources',
                'filters',
                'pageSeo',
                'heading',
                'resultsHeading',
                'taxonomy',
            ],
            onFinish: options.onFinish,
        },
    );
}
