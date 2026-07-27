import { Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Dices,
    ExternalLink,
    Home,
    Library,
    Menu,
    Moon,
    Search,
    Sparkles,
    Star,
    Sun,
    XIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import type { AuthDialogView } from '@/components/auth/auth-dialog';
import { SiteLogo } from '@/components/site/site-logo';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetClose,
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
import { home, search } from '@/routes';
import type { User } from '@/types';
import type { NavigationMenuItem } from '@/types/navigation';

const navigationIcons: Record<string, LucideIcon> = {
    BookOpen,
    Dices,
    ExternalLink,
    Home,
    Library,
    Search,
    Sparkles,
    Star,
};

const navLinkClassName = cn(
    'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium',
    'text-foreground/75 transition-[color,background-color]',
    'hover:bg-primary/10 hover:text-primary',
    'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
);

const softButtonClassName = cn(
    'hover:bg-foreground/10 hover:text-foreground',
    'aria-expanded:bg-foreground/5 aria-expanded:text-foreground',
);

const iconButtonClassName = cn(
    'inline-flex size-9 items-center justify-center rounded-md text-foreground',
    'transition-colors outline-none select-none',
    'hover:bg-foreground/10',
    'focus-visible:ring-2 focus-visible:ring-ring/50',
);

function menuItemPath(url: string): string {
    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            return new URL(url).pathname || '/';
        } catch {
            return url;
        }
    }

    return url.split('?')[0] || '/';
}

function isExternalMenuUrl(url: string): boolean {
    return url.startsWith('http://') || url.startsWith('https://');
}

function isNavigationItemActive(
    item: NavigationMenuItem,
    items: NavigationMenuItem[],
    currentPath: string,
): boolean {
    if (item.match === 'none' || isExternalMenuUrl(item.url)) {
        return false;
    }

    const targetPath = menuItemPath(item.url);

    if (item.match === 'exact' || targetPath === '/') {
        return currentPath === targetPath || (targetPath === '/' && currentPath === '');
    }

    const matches =
        currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);

    if (!matches) {
        return false;
    }

    return !items.some((other) => {
        if (other === item || isExternalMenuUrl(other.url)) {
            return false;
        }

        const otherPath = menuItemPath(other.url);

        return (
            otherPath.length > targetPath.length &&
            (currentPath === otherPath ||
                currentPath.startsWith(`${otherPath}/`))
        );
    });
}

function NavLinks({
    className,
    onNavigate,
}: {
    className?: string;
    onNavigate?: () => void;
}) {
    const { url, props } = usePage();
    const currentPath = url.split('?')[0] || '/';
    const navItems = props.navigationMenu ?? [];

    return (
        <nav className={cn('flex items-center gap-0.5', className)}>
            {navItems.map((item) => {
                const Icon = item.icon ? navigationIcons[item.icon] : undefined;
                const active = isNavigationItemActive(
                    item,
                    navItems,
                    currentPath,
                );
                const external = isExternalMenuUrl(item.url) || item.openInNewTab;
                const content = (
                    <>
                        {Icon ? <Icon className="size-3.5 shrink-0" /> : null}
                        {item.label}
                    </>
                );
                const itemClassName = cn(
                    navLinkClassName,
                    active &&
                        'bg-primary/12 text-primary hover:bg-primary/15 hover:text-primary',
                );

                if (external) {
                    return (
                        <a
                            key={`${item.label}-${item.url}`}
                            href={item.url}
                            className={itemClassName}
                            onClick={onNavigate}
                            target={item.openInNewTab ? '_blank' : undefined}
                            rel={
                                item.openInNewTab
                                    ? 'noopener noreferrer'
                                    : undefined
                            }
                            aria-current={active ? 'page' : undefined}
                        >
                            {content}
                        </a>
                    );
                }

                return (
                    <Link
                        key={`${item.label}-${item.url}`}
                        href={item.url}
                        className={itemClassName}
                        onClick={onNavigate}
                        aria-current={active ? 'page' : undefined}
                        // One-shot links (match: none) must not be prefetched.
                        prefetch={item.match === 'none' ? false : undefined}
                    >
                        {content}
                    </Link>
                );
            })}
        </nav>
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
                        'focus:outline-none focus-visible:ring-0 focus-visible:outline-none',
                    )}
                    aria-label="Open user menu"
                >
                    <UserAvatar
                        user={user}
                        className="size-8"
                        fallbackClassName="rounded-full bg-accent text-xs text-accent-foreground"
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
    const { openAuthDialog } = useAuthDialog();
    const [open, setOpen] = useState(false);
    const closeMenu = () => setOpen(false);
    const openAuth = (view: AuthDialogView) => {
        openAuthDialog(view, { redirect: page.url });
    };
    const openMobileAuth = (view: AuthDialogView) => {
        closeMenu();
        requestAnimationFrame(() => openAuth(view));
    };

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
        <header className="sticky top-0 z-40 border-b border-border/80 bg-surface-raised/85 backdrop-blur-md supports-backdrop-filter:bg-surface-raised/70">
            <div className="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
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
                        showCloseButton={false}
                        className="w-[85vw] max-w-xs gap-0 bg-surface-raised p-0"
                    >
                        <SheetTitle className="sr-only">
                            Navigation menu
                        </SheetTitle>
                        <div className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-border/80 px-4">
                            <Link
                                href={home()}
                                className="min-w-0 transition-opacity hover:opacity-80"
                                onClick={closeMenu}
                            >
                                <SiteLogo />
                            </Link>
                            <SheetClose asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    className="shrink-0"
                                    aria-label="Close menu"
                                >
                                    <XIcon />
                                    <span className="sr-only">Close</span>
                                </Button>
                            </SheetClose>
                        </div>

                        <div className="flex min-h-0 flex-1 flex-col px-3 py-4">
                            <NavLinks
                                className="flex-col items-stretch gap-1 [&>a]:h-10 [&>a]:justify-start [&>a]:px-3"
                                onNavigate={closeMenu}
                            />

                            {!auth.user && (
                                <div className="mt-auto flex flex-col gap-3 pt-6">
                                    <Separator className="bg-border" />
                                    <div className="grid gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className={cn(
                                                softButtonClassName,
                                                'w-full',
                                            )}
                                            onClick={() =>
                                                openMobileAuth('login')
                                            }
                                        >
                                            Log in
                                        </Button>
                                        <Button
                                            variant="auth"
                                            size="sm"
                                            className="w-full"
                                            onClick={() =>
                                                openMobileAuth('register')
                                            }
                                        >
                                            Sign up
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
                        className="shrink-0 transition-opacity hover:opacity-80"
                    >
                        <SiteLogo />
                    </Link>

                    <NavLinks className="hidden md:flex" />
                </div>

                <div className="flex items-center gap-3">
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

                        <ThemeToggle />
                    </div>

                    {auth.user ? (
                        <UserAvatarMenu user={auth.user} />
                    ) : (
                        <div className="hidden items-center gap-1.5 md:flex">
                            <Button
                                variant="ghost"
                                size="sm"
                                className={softButtonClassName}
                                onClick={() => openAuth('login')}
                            >
                                Log in
                            </Button>
                            <Button
                                variant="auth"
                                size="sm"
                                onClick={() => openAuth('register')}
                            >
                                Sign up
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
