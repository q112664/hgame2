import { Link } from '@inertiajs/react';
import { Eye, Sparkles } from 'lucide-react';
import { LazyThumbnail } from '@/components/site/lazy-thumbnail';
import { formatViews } from '@/lib/resource-formatters';
import { cn } from '@/lib/utils';
import { show as resourceDetails } from '@/routes/resources';
import type { GameCard } from '@/types/resources';

type Props = {
    resources: GameCard[];
    title?: string;
};

/** Simple recommendations for the Details tab — compact landscape cards. */
export function RelatedResources({
    resources,
    title = 'Related games',
}: Props) {
    if (resources.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="related-heading">
            <div className="mb-3 flex items-center gap-2 sm:mb-3.5">
                <Sparkles
                    className="size-4 text-primary sm:size-5"
                    aria-hidden
                />
                <h2
                    id="related-heading"
                    className="font-heading text-lg font-semibold text-foreground sm:text-xl"
                >
                    {title}
                </h2>
            </div>

            <ul className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                {resources.map((resource, index) => (
                    <li key={resource.id} className="min-w-0">
                        <Link
                            href={resourceDetails(resource.id)}
                            prefetch
                            className={cn(
                                'group relative block overflow-hidden rounded-lg',
                                'aspect-[16/10] bg-muted ring-1 ring-border/70',
                                'transition-[ring-color] duration-150',
                                'hover:ring-foreground/15',
                                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                            )}
                        >
                            <LazyThumbnail
                                src={resource.thumbnail}
                                fallbackSrc={resource.thumbnailFallback}
                                alt={resource.title}
                                priority={index < 4}
                            />

                            <div
                                className={cn(
                                    'absolute inset-x-0 bottom-0',
                                    'bg-gradient-to-t from-black/85 via-black/45 to-transparent',
                                    'px-2.5 pt-8 pb-2.5 sm:px-3 sm:pb-3',
                                )}
                            >
                                <p className="line-clamp-2 font-heading text-[13px] leading-snug font-semibold tracking-tight text-white sm:text-sm">
                                    {resource.title}
                                </p>
                                <div className="mt-1.5 flex items-center justify-between gap-2">
                                    <p className="min-w-0 truncate text-[11px] leading-none text-white/80">
                                        {resource.category}
                                    </p>
                                    <p className="inline-flex shrink-0 items-center gap-1 text-[11px] leading-none tabular-nums text-white/80">
                                        <Eye
                                            className="size-3 shrink-0 opacity-80"
                                            aria-hidden
                                        />
                                        {formatViews(resource.views)}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
