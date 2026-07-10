import { Link, usePage } from '@inertiajs/react';
import { Menu, Moon, Bell, Search, Sun, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { SiteSearchDialog } from '@/components/site/site-search-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { home, login, register } from '@/routes';
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
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex size-9 items-center justify-center rounded-full',
                        'transition-colors outline-none select-none',
                        'hover:bg-black/5 dark:hover:bg-white/10',
                        'focus-visible:ring-2 focus-visible:ring-ring/50',
                    )}
                    aria-label="Open user menu"
                >
                    <Avatar className="size-8 overflow-hidden rounded-full">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="rounded-full bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end">
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function SiteHeader() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const closeMenu = () => setOpen(false);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                setSearchOpen(true);
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    return (
        <header className="sticky top-0 z-40 border-b border-black/5 bg-background/75 backdrop-blur-md dark:border-white/10 supports-backdrop-filter:bg-background/65">
            <SiteSearchDialog open={searchOpen} onOpenChange={setSearchOpen} />
            <Collapsible open={open} onOpenChange={setOpen}>
                <div className="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className={cn(
                                iconButtonClassName,
                                '-ml-1 shrink-0 aria-expanded:bg-black/5 dark:aria-expanded:bg-white/10 md:hidden',
                            )}
                            aria-label={open ? 'Close menu' : 'Open menu'}
                        >
                            {open ? (
                                <X className="size-4" />
                            ) : (
                                <Menu className="size-4" />
                            )}
                        </button>
                    </CollapsibleTrigger>

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
                                <button
                                    type="button"
                                    className={iconButtonClassName}
                                    onClick={() => setSearchOpen(true)}
                                    aria-label="Search resources"
                                >
                                    <Search className="size-4" />
                                </button>
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
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href={register()}>Sign up</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                <CollapsibleContent className="border-t border-black/5 dark:border-white/10 md:hidden">
                    <div className="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-2 sm:px-6">
                        <Button
                            variant="ghost"
                            size="sm"
                            className={cn(
                                softButtonClassName,
                                'h-9 w-full justify-start gap-2',
                            )}
                            onClick={() => {
                                closeMenu();
                                setSearchOpen(true);
                            }}
                        >
                            <Search className="size-4" />
                            Search
                        </Button>

                        <NavLinks
                            className="grid grid-cols-2 gap-1 [&>a]:h-9 [&>a]:justify-center"
                            onNavigate={closeMenu}
                        />

                        {!auth.user && (
                            <>
                                <Separator className="bg-black/8 dark:bg-white/10" />

                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className={softButtonClassName}
                                        asChild
                                    >
                                        <Link
                                            href={login()}
                                            onClick={closeMenu}
                                        >
                                            Log in
                                        </Link>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <Link
                                            href={register()}
                                            onClick={closeMenu}
                                        >
                                            Sign up
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </header>
    );
}
