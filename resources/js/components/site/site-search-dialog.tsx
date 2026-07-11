import { Link, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { details as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type SearchResource = Pick<GameCard, 'id' | 'title' | 'thumbnail'>;

type PageProps = {
    searchResources: SearchResource[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function SiteSearchDialog({ open, onOpenChange }: Props) {
    const { searchResources } = usePage<PageProps>().props;
    const [query, setQuery] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const frame = requestAnimationFrame(() => inputRef.current?.focus());

        return () => cancelAnimationFrame(frame);
    }, [open]);

    const results = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();

        if (normalizedQuery === '') {
            return [];
        }

        return searchResources
            .filter((resource) =>
                resource.title.toLowerCase().includes(normalizedQuery),
            )
            .slice(0, 8);
    }, [query, searchResources]);

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            setQuery('');
        }

        onOpenChange(nextOpen);
    };

    const closeDialog = () => handleOpenChange(false);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogHeader className="gap-1 border-b border-foreground/10 px-4 py-4 text-left">
                    <DialogTitle>Search resources</DialogTitle>
                    <DialogDescription>
                        Find visual novels and galgame resources by title.
                    </DialogDescription>
                </DialogHeader>

                <div className="border-b border-foreground/10 px-4 py-3">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            ref={inputRef}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search by title..."
                            className="pl-8"
                            autoComplete="off"
                        />
                    </div>
                </div>

                <div className="max-h-80 overflow-y-auto px-2 py-2">
                    {query.trim() === '' ? (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            Start typing to search resources.
                        </p>
                    ) : results.length === 0 ? (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            No resources found for &ldquo;{query.trim()}&rdquo;.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-1">
                            {results.map((resource) => (
                                <li key={resource.id}>
                                    <Link
                                        href={resourceDetails(resource.id)}
                                        onClick={closeDialog}
                                        className="flex items-center gap-3 rounded-md px-2 py-2 transition-colors hover:bg-muted"
                                        prefetch
                                    >
                                        <div className="aspect-video w-16 shrink-0 overflow-hidden rounded-md bg-muted">
                                            <img
                                                src={resource.thumbnail}
                                                alt=""
                                                className="size-full object-cover"
                                                loading="lazy"
                                                referrerPolicy="no-referrer"
                                            />
                                        </div>
                                        <span className="line-clamp-2 text-sm font-medium text-foreground">
                                            {resource.title}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
