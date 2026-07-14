import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpDown,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    Search,
    Tags,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ResourceCard } from '@/components/site/resource-card';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { SiteLayout } from '@/layouts/site-layout';
import { cn } from '@/lib/utils';
import { index as resourcesIndex } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type FilterOption = {
    name: string;
    slug: string;
};

type LanguageOption = {
    name: string;
    code: string;
};

type SortOption = 'latest' | 'oldest' | 'title' | 'views';

type ResourceFilters = {
    category: string | null;
    platform: string | null;
    language: string | null;
    tags: string[];
    sort: SortOption;
};

type FilterOptions = {
    categories: FilterOption[];
    platforms: FilterOption[];
    languages: LanguageOption[];
    tags: FilterOption[];
};

type PaginatedResources = {
    data: GameCard[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

type Props = {
    resources: PaginatedResources;
    filters: ResourceFilters;
    filterOptions: FilterOptions;
};

const ALL_VALUE = '__all__';

const SORT_OPTIONS: Array<{ value: SortOption; label: string }> = [
    { value: 'latest', label: 'Latest' },
    { value: 'oldest', label: 'Oldest' },
    { value: 'title', label: 'Title A–Z' },
    { value: 'views', label: 'Most viewed' },
];

const DEFAULT_FILTERS: ResourceFilters = {
    category: null,
    platform: null,
    language: null,
    tags: [],
    sort: 'latest',
};

function FilterMenu({
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

function SortMenu({
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

function filterQuery(
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

function visitFilters(
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

function TagFilterDialog({
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

function scrollToResourceResults() {
    document.getElementById('resource-results')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function ResourcePagination({
    resources,
    filters,
}: {
    resources: PaginatedResources;
    filters: ResourceFilters;
}) {
    if (resources.last_page <= 1) {
        return null;
    }

    const pageLinks = resources.links.filter(
        (link) =>
            link.label !== '&laquo; Previous' &&
            link.label !== 'Next &raquo;' &&
            !link.label.includes('Previous') &&
            !link.label.includes('Next'),
    );

    const paginationLinkProps = {
        preserveState: true,
        preserveScroll: true,
        only: ['resources', 'filters'] as const,
        onSuccess: scrollToResourceResults,
    };

    return (
        <nav
            className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between"
            aria-label="Pagination"
        >
            <p className="text-sm text-muted-foreground">
                {resources.from && resources.to
                    ? `Showing ${resources.from}–${resources.to} of ${resources.total}`
                    : `${resources.total} results`}
            </p>

            <div className="flex flex-wrap items-center justify-center gap-1">
                {resources.current_page > 1 ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        asChild
                    >
                        <Link
                            href={resourcesIndex.url({
                                query: filterQuery(
                                    filters,
                                    resources.current_page - 1,
                                ),
                            })}
                            {...paginationLinkProps}
                        >
                            <ChevronLeft data-icon="inline-start" />
                            Prev
                        </Link>
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        disabled
                    >
                        <ChevronLeft data-icon="inline-start" />
                        Prev
                    </Button>
                )}

                {pageLinks.map((link, index) => {
                    const label = link.label
                        .replace(/&laquo;|&raquo;/g, '')
                        .trim();

                    if (link.url === null) {
                        return (
                            <span
                                key={`ellipsis-${index}`}
                                className="inline-flex size-8 items-center justify-center text-sm text-muted-foreground"
                            >
                                …
                            </span>
                        );
                    }

                    const page = Number(label);

                    return (
                        <Button
                            key={`${label}-${index}`}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            className={cn(
                                'size-8 p-0 shadow-none',
                                !link.active &&
                                    'border-foreground/10 bg-card',
                            )}
                            asChild
                        >
                            <Link
                                href={
                                    Number.isFinite(page)
                                        ? resourcesIndex.url({
                                              query: filterQuery(
                                                  filters,
                                                  page,
                                              ),
                                          })
                                        : link.url
                                }
                                {...paginationLinkProps}
                                aria-current={
                                    link.active ? 'page' : undefined
                                }
                            >
                                {label}
                            </Link>
                        </Button>
                    );
                })}

                {resources.current_page < resources.last_page ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        asChild
                    >
                        <Link
                            href={resourcesIndex.url({
                                query: filterQuery(
                                    filters,
                                    resources.current_page + 1,
                                ),
                            })}
                            {...paginationLinkProps}
                        >
                            Next
                            <ChevronRight data-icon="inline-end" />
                        </Link>
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-foreground/10 bg-card shadow-none"
                        disabled
                    >
                        Next
                        <ChevronRight data-icon="inline-end" />
                    </Button>
                )}
            </div>
        </nav>
    );
}

export default function ResourcesIndex({
    resources,
    filters,
    filterOptions,
}: Props) {
    const [isPending, setIsPending] = useState(false);
    const selectedTagNames = useMemo(() => {
        const bySlug = new Map(
            filterOptions.tags.map((tag) => [tag.slug, tag.name]),
        );

        return filters.tags.map((slug) => ({
            slug,
            name: bySlug.get(slug) ?? slug,
        }));
    }, [filterOptions.tags, filters.tags]);

    const hasActiveFilters =
        Boolean(filters.category) ||
        Boolean(filters.platform) ||
        Boolean(filters.language) ||
        filters.tags.length > 0;

    const applyFilters = (next: ResourceFilters) => {
        setIsPending(true);
        visitFilters(next, {
            onFinish: () => setIsPending(false),
        });
    };

    return (
        <SiteLayout>
            <Head title="Resources" />

            <section className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-2">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        Resources
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Browse and filter published visual novel resources
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <FilterMenu
                            label="Category"
                            value={filters.category}
                            allLabel="All categories"
                            options={filterOptions.categories.map(
                                (category) => ({
                                    value: category.slug,
                                    label: category.name,
                                }),
                            )}
                            onChange={(category) =>
                                applyFilters({ ...filters, category })
                            }
                        />

                        <FilterMenu
                            label="Platform"
                            value={filters.platform}
                            allLabel="All platforms"
                            options={filterOptions.platforms.map(
                                (platform) => ({
                                    value: platform.slug,
                                    label: platform.name,
                                }),
                            )}
                            onChange={(platform) =>
                                applyFilters({ ...filters, platform })
                            }
                        />

                        <FilterMenu
                            label="Language"
                            value={filters.language}
                            allLabel="All languages"
                            options={filterOptions.languages.map(
                                (language) => ({
                                    value: language.code,
                                    label: language.name,
                                }),
                            )}
                            onChange={(language) =>
                                applyFilters({ ...filters, language })
                            }
                        />

                        <div className="flex flex-col gap-1.5">
                            <Label className="text-xs text-muted-foreground">
                                Tags
                            </Label>
                            <TagFilterDialog
                                options={filterOptions.tags}
                                selected={filters.tags}
                                onApply={(tags) =>
                                    applyFilters({ ...filters, tags })
                                }
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                            {selectedTagNames.map((tag) => (
                                <Badge
                                    key={tag.slug}
                                    variant="secondary"
                                    className="h-7 gap-1 rounded-md pr-1 pl-2.5 text-xs font-medium"
                                >
                                    {tag.name}
                                    <button
                                        type="button"
                                        className="inline-flex size-5 items-center justify-center rounded-sm text-muted-foreground hover:bg-foreground/10 hover:text-foreground"
                                        aria-label={`Remove ${tag.name}`}
                                        onClick={() =>
                                            applyFilters({
                                                ...filters,
                                                tags: filters.tags.filter(
                                                    (slug) =>
                                                        slug !== tag.slug,
                                                ),
                                            })
                                        }
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                </Badge>
                            ))}
                        </div>

                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 gap-2 border-foreground/10 bg-card shadow-none"
                                disabled={!hasActiveFilters}
                                onClick={() =>
                                    applyFilters({
                                        ...DEFAULT_FILTERS,
                                        sort: filters.sort,
                                    })
                                }
                            >
                                <RotateCcw className="size-4 text-muted-foreground" />
                                Clear
                            </Button>
                            <SortMenu
                                value={filters.sort}
                                onChange={(sort) =>
                                    applyFilters({ ...filters, sort })
                                }
                            />
                        </div>
                    </div>
                </div>

                {isPending ? (
                    <div
                        id="resource-results"
                        className="flex scroll-mt-20 justify-center py-16"
                        aria-busy="true"
                        aria-label="Loading resources"
                    >
                        <Spinner className="size-8 text-muted-foreground" />
                    </div>
                ) : resources.data.length > 0 ? (
                    <div
                        id="resource-results"
                        className="flex scroll-mt-20 flex-col gap-8"
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {resources.data.map((resource) => (
                                <ResourceCard
                                    key={resource.id}
                                    resource={resource}
                                />
                            ))}
                        </div>

                        <ResourcePagination
                            resources={resources}
                            filters={filters}
                        />
                    </div>
                ) : (
                    <p
                        id="resource-results"
                        className="scroll-mt-20 py-16 text-center text-sm text-muted-foreground"
                    >
                        No resources match these filters
                    </p>
                )}
            </section>
        </SiteLayout>
    );
}
