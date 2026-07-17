import { Head } from '@inertiajs/react';
import { RotateCcw, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ResourceCard } from '@/components/site/resource-card';
import {
    DEFAULT_FILTERS,
    FilterMenu,
    SortMenu,
    TagFilterDialog,
    visitFilters,
} from '@/components/site/resource-filter-controls';
import type {
    FilterOptions,
    ResourceFilters,
} from '@/components/site/resource-filter-controls';
import { ResourcePagination } from '@/components/site/resource-pagination';
import type { PaginatedResources } from '@/components/site/resource-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { SiteLayout } from '@/layouts/site-layout';

type Props = {
    resources: PaginatedResources;
    filters: ResourceFilters;
    filterOptions: FilterOptions;
};

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
                                                    (slug) => slug !== tag.slug,
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
                                className="h-9 gap-2 border-border bg-card shadow-none"
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
