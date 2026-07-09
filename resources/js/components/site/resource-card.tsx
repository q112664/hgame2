import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Apple, Eye, Monitor, Smartphone, Terminal } from 'lucide-react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { MockResource } from '@/data/mock-resources';
import { cn } from '@/lib/utils';
import { show as resourceShow } from '@/routes/resources';

type Props = {
    resource: MockResource;
};

const platformIcons: Record<string, LucideIcon> = {
    Windows: Monitor,
    Android: Smartphone,
    macOS: Apple,
    Linux: Terminal,
};

const languageAbbrev: Record<string, string> = {
    Chinese: 'CN',
    Japanese: 'JP',
    English: 'EN',
};

const categoryAbbrev: Record<string, string> = {
    'Visual Novel': 'VN',
};

const overlayChipClassName = cn(
    'inline-flex h-6 items-center justify-center rounded-md px-2',
    'bg-background/80 text-xs font-medium text-foreground',
    'ring-1 ring-foreground/10 backdrop-blur-md',
);

function getPlatformIcon(platform: string): LucideIcon {
    return platformIcons[platform] ?? Monitor;
}

function abbreviateLanguage(language: string): string {
    return languageAbbrev[language] ?? language.slice(0, 2).toUpperCase();
}

function abbreviateCategory(category: string): string {
    return categoryAbbrev[category] ?? category;
}

function formatViews(views: number): string {
    return new Intl.NumberFormat('en-US').format(views);
}

function formatDate(date: string): string {
    const [year, month, day] = date.split('-');

    return `${year}-${month}-${day}`;
}

export function ResourceCard({ resource }: Props) {
    const PlatformIcon = getPlatformIcon(resource.platform);

    return (
        <Card
            size="sm"
            className="h-full gap-0 rounded-md py-0 transition-[ring-color] duration-150 hover:ring-foreground/15"
        >
            <Link
                href={resourceShow(resource.id)}
                className="group flex h-full flex-col"
                prefetch
            >
                <div className="relative aspect-video overflow-hidden rounded-t-md bg-muted">
                    <img
                        src={resource.thumbnail}
                        alt={resource.title}
                        className="size-full object-cover"
                        loading="lazy"
                        referrerPolicy="no-referrer"
                    />

                    <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-2">
                        <span className={overlayChipClassName}>
                            {abbreviateCategory(resource.category)}
                        </span>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    className={cn(
                                        overlayChipClassName,
                                        'size-6 px-0',
                                    )}
                                    aria-label={resource.platform}
                                >
                                    <PlatformIcon className="size-3.5" />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                {resource.platform}
                            </TooltipContent>
                        </Tooltip>
                    </div>

                    <div className="absolute inset-x-0 bottom-0 flex justify-end p-2">
                        <span className={overlayChipClassName}>
                            {abbreviateLanguage(resource.language)}
                        </span>
                    </div>
                </div>

                <CardHeader className="gap-1.5 pt-3 pb-0">
                    <CardTitle className="line-clamp-2 text-sm leading-snug">
                        {resource.title}
                    </CardTitle>
                </CardHeader>

                <CardContent className="mt-auto flex items-center justify-between gap-2 pt-1.5 pb-3 text-xs text-muted-foreground">
                    <time dateTime={resource.publishedAt}>
                        {formatDate(resource.publishedAt)}
                    </time>
                    <span className="inline-flex items-center gap-1">
                        <Eye className="size-3.5" />
                        {formatViews(resource.views)}
                    </span>
                </CardContent>
            </Link>
        </Card>
    );
}
