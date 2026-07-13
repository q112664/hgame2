import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Menu, Moon, Search, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { home, login, register, search } from '@/routes';
import type { User } from '@/types';

type NavItem = {
    title: string;
    href: string;
    external?: boolean;
};

const navItems: NavItem[] = [
    { title: 'Home', href: home().url },
    { title: 'Latest', href: '#latest', external: true },
    { title: 'Categories', href: '#categories', external: true },
];

const navLinkClassName = cn(
    'inline-flex h-8 items-center rounded-md px-2.5 text-sm font-medium',
    'text-foreground transition-colors',
    'hover:bg-black/5 dark:hover:bg-white/10',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
);

const softButtonClassName = cn(
    'hover:bg-black/5 hover:text-foreground',
    'aria-expanded:bg-black/5 aria-expanded:text-foreground',
    'dark:hover:bg-white/10 dark:aria-expanded:bg-white/10',
);

const iconButtonClassName = cn(
    'inline-flex size-9 items-center justify-center rounded-md text-foreground',
    'transition-colors outline-none select-none',
    'hover:bg-black/5 dark:hover:bg-white/10',
    'focus-visible:ring-2 focus-visible:ring-ring/50',
);

function NavLinks({
    className,
    onNavigate,
}: {
    className?: string;
    onNavigate?: () => void;
}) {
    return (
        <nav className={cn('flex items-center gap-0.5', className)}>
            {navItems.map((item) =>
                item.external ? (
                    <a
                        key={item.title}
                        href={item.href}
                        className={navLinkClassName}
                        onClick={onNavigate}
                    >
                        {item.title}
                    </a>
                ) : (
                    <Link
                        key={item.title}
                        href={item.href}
                        className={navLinkClassName}
                        onClick={onNavigate}
                    >
                        {item.title}
                    </Link>
                ),
            )}
        </nav>
    );
}

function NotificationButton() {
    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className={iconButtonClassName}
                            aria-label="Notifications"
                        >
                            <Bell className="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent side="bottom" sideOffset={4}>
                    Notifications
                </TooltipContent>
            </Tooltip>
            <DropdownMenuContent className="w-80" align="end">
                <div className="border-b border-foreground/10 px-3 py-2">
                    <p className="text-sm font-medium text-foreground">
                        Notifications
                    </p>
                </div>
                <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                    No notifications yet.
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const actionLabel = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className={iconButtonClassName}
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    aria-label={actionLabel}
                >
                    {isDark ? (
                        <Sun className="size-4" />
                    ) : (
                        <Moon className="size-4" />
                    )}
                </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" sideOffset={4}>
                {actionLabel}
            </TooltipContent>
        </Tooltip>
    );
}

function UserAvatarMenu({ user }: { user: User }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex items-center gap-2 outline-none select-none',
                        'opacity-100 transition-opacity hover:opacity-70',
                        'focus:outline-none focus-visible:outline-none focus-visible:ring-0',
                    )}
                    aria-label="Open user menu"
                >
                    <UserAvatar
                        user={user}
                        className="size-8"
                        fallbackClassName="rounded-full bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white"
                    />
                    <span className="hidden max-w-32 truncate text-sm font-medium text-foreground md:inline">
                        {user.name}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end" sideOffset={8}>
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function SiteHeader() {
    const page = usePage();
    const { auth } = page.props;
    const loginHref = login({ query: { redirect: page.url } });
    const registerHref = register({ query: { redirect: page.url } });
    const [open, setOpen] = useState(false);
    const closeMenu = () => setOpen(false);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                router.visit(search());
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    return (
        <header className="sticky top-0 z-40 border-b border-black/5 bg-background/75 backdrop-blur-md dark:border-white/10 supports-backdrop-filter:bg-background/65">
            <div className="mx-auto flex h-14 max-w-[90rem] items-center gap-4 px-4 sm:px-6 lg:px-8">
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetTrigger asChild>
                        <button
                            type="button"
                            className={cn(
                                iconButtonClassName,
                                '-ml-1 shrink-0 md:hidden',
                            )}
                            aria-label="Open menu"
                        >
                            <Menu className="size-4" />
                        </button>
                    </SheetTrigger>
                    <SheetContent
                        side="left"
                        className="w-[85vw] max-w-xs gap-0 bg-background p-0"
                    >
                        <SheetTitle className="sr-only">
                            Navigation menu
                        </SheetTitle>
                        <div className="flex h-14 shrink-0 items-center border-b border-black/5 px-4 dark:border-white/10">
                            <Link
                                href={home()}
                                className="font-heading text-lg font-semibold tracking-tight text-foreground transition-opacity hover:opacity-80"
                                onClick={closeMenu}
                            >
                                hgame
                            </Link>
                        </div>

                        <div className="flex min-h-0 flex-1 flex-col px-3 py-4">
                            <NavLinks
                                className="flex-col items-stretch gap-1 [&>a]:h-10 [&>a]:justify-start [&>a]:px-3"
                                onNavigate={closeMenu}
                            />

                            {!auth.user && (
                                <div className="mt-auto flex flex-col gap-3 pt-6">
                                    <Separator className="bg-black/8 dark:bg-white/10" />
                                    <div className="grid gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className={cn(
                                                softButtonClassName,
                                                'w-full',
                                            )}
                                            asChild
                                        >
                                            <Link
                                                href={loginHref}
                                                onClick={closeMenu}
                                            >
                                                Log in
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            className="w-full"
                                            asChild
                                        >
                                            <Link
                                                href={registerHref}
                                                onClick={closeMenu}
                                            >
                                                Sign up
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 items-center gap-6">
                    <Link
                        href={home()}
                        className="font-heading shrink-0 text-lg font-semibold tracking-tight text-foreground transition-opacity hover:opacity-80"
                    >
                        hgame
                    </Link>

                    <NavLinks className="hidden md:flex" />
                </div>

                <div className="flex items-center gap-1.5">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Link
                                href={search()}
                                className={iconButtonClassName}
                                aria-label="Search resources"
                                prefetch
                            >
                                <Search className="size-4" />
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" sideOffset={4}>
                            Search resources
                        </TooltipContent>
                    </Tooltip>

                    <NotificationButton />

                    <ThemeToggle />

                    {auth.user ? (
                        <UserAvatarMenu user={auth.user} />
                    ) : (
                        <div className="hidden items-center gap-1.5 md:flex">
                            <Button
                                variant="ghost"
                                size="sm"
                                className={softButtonClassName}
                                asChild
                            >
                                <Link href={loginHref}>Log in</Link>
                            </Button>
                            <Button size="sm" asChild>
                                <Link href={registerHref}>Sign up</Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
