import { Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChevronDown,
    Dices,
    ExternalLink,
    Gamepad2,
    Home,
    Languages,
    Library,
    Menu,
    Moon,
    Search,
    Sparkles,
    Star,
    Sun,
    Tags,
    XIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import type { AuthDialogView } from '@/components/auth/auth-dialog';
import { NotificationBell } from '@/components/site/notification-bell';
import { SiteLogo } from '@/components/site/site-logo';
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
import {
    genre as resourcesGenre,
    language as resourcesLanguage,
} from '@/routes/resources';
import type { User } from '@/types';
import type {
    NavigationMenuItem as SiteNavItem,
    TaxonomyNavLink,
} from '@/types/navigation';

const navigationIcons: Record<string, LucideIcon> = {
    BookOpen,
    Dices,
    ExternalLink,
    Gamepad2,
    Home,
    Library,
    Search,
    Sparkles,
    Star,
    Tags,
};

const navLinkClassName = cn(
    'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 py-0 text-sm font-medium',
    'text-foreground/75 transition-[color,background-color]',
    'hover:bg-primary/10 hover:text-primary',
    'outline-none focus-visible:ring-0 focus-visible:outline-none',
);

const navLinkActiveClassName = cn(
    'bg-primary/12 text-primary hover:bg-primary/15 hover:text-primary',
);

type NavLinksVariant = 'bar' | 'sheet';

const taxonomyItemClassName = cn(
    'flex w-full cursor-pointer items-center rounded-md px-2.5 py-1.5 text-left text-sm',
    'text-foreground/75 no-underline outline-none',
    'hover:bg-primary/10 hover:text-primary',
);

const softButtonClassName = cn(
    'hover:bg-foreground/10 hover:text-foreground',
    'aria-expanded:bg-foreground/5 aria-expanded:text-foreground',
);

/** Match logo / avatar footprint (32px) inside the h-14 bar. */
const iconButtonClassName = cn(
    'inline-flex size-8 items-center justify-center rounded-md text-foreground',
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
    item: SiteNavItem,
    items: SiteNavItem[],
    currentPath: string,
): boolean {
    if (item.match === 'none' || isExternalMenuUrl(item.url)) {
        return false;
    }

    const targetPath = menuItemPath(item.url);

    if (item.match === 'exact' || targetPath === '/') {
        return (
            currentPath === targetPath ||
            (targetPath === '/' && currentPath === '')
        );
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

function pathHasPrefix(path: string, prefix: string): boolean {
    return path === prefix || path.startsWith(`${prefix}/`);
}

function navHasPathPrefix(items: SiteNavItem[], prefix: string): boolean {
    return items.some((item) => pathHasPrefix(menuItemPath(item.url), prefix));
}

function taxonomyFlyoutInsertIndex(items: SiteNavItem[]): number {
    const catalogIndex = items.findIndex(
        (item) => menuItemPath(item.url) === '/resources',
    );

    if (catalogIndex !== -1) {
        return catalogIndex + 1;
    }

    const homeIndex = items.findIndex((item) => menuItemPath(item.url) === '/');

    if (homeIndex !== -1) {
        return homeIndex + 1;
    }

    return 0;
}

function TaxonomyFlyout({
    menuKey,
    label,
    icon: Icon,
    items,
    hrefFor,
    currentPath,
    activePrefix,
    variant,
    openKey,
    onOpenKeyChange,
    onNavigate,
}: {
    menuKey: string;
    label: string;
    icon: LucideIcon;
    items: TaxonomyNavLink[];
    hrefFor: (item: TaxonomyNavLink) => string;
    currentPath: string;
    activePrefix: string;
    variant: NavLinksVariant;
    openKey: string | null;
    onOpenKeyChange: (key: string | null) => void;
    onNavigate?: () => void;
}) {
    const open = openKey === menuKey;
    const active = pathHasPrefix(currentPath, activePrefix);
    const triggerClassName = cn(
        navLinkClassName,
        'group cursor-pointer outline-none focus-visible:ring-0',
        variant === 'sheet' && 'h-10 w-full justify-start px-3',
    );

    const links = items.map((item) => {
        const href = hrefFor(item);
        const itemActive = currentPath === menuItemPath(href);
        const itemClassName =
            variant === 'sheet'
                ? cn(
                      navLinkClassName,
                      'h-10 w-full justify-start px-3 font-normal',
                  )
                : taxonomyItemClassName;

        return (
            <Link
                key={item.value}
                href={href}
                role={variant === 'sheet' ? undefined : 'menuitem'}
                className={itemClassName}
                aria-current={itemActive ? 'page' : undefined}
                prefetch
                onClick={() => {
                    onOpenKeyChange(null);
                    onNavigate?.();
                }}
            >
                <span className="truncate">{item.name}</span>
            </Link>
        );
    });

    if (variant === 'sheet') {
        return (
            <Collapsible
                defaultOpen={active}
                className="flex w-full flex-col gap-1"
            >
                <CollapsibleTrigger className={triggerClassName}>
                    <Icon className="size-3.5 shrink-0" />
                    {label}
                    <ChevronDown className="ml-auto size-3 shrink-0 text-muted-foreground transition-transform group-aria-expanded:rotate-180 group-data-[state=open]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent className="flex flex-col gap-1 pl-3">
                    {links}
                </CollapsibleContent>
            </Collapsible>
        );
    }

    return (
        <div className="relative">
            <button
                type="button"
                className={triggerClassName}
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => onOpenKeyChange(open ? null : menuKey)}
            >
                <Icon className="size-3.5 shrink-0" />
                {label}
                <ChevronDown className="size-3 shrink-0 text-muted-foreground transition-transform group-aria-expanded:rotate-180" />
            </button>
            {open ? (
                <div
                    role="menu"
                    className="absolute top-full left-0 mt-2 max-h-80 min-w-44 overflow-y-auto rounded-md bg-popover p-1 text-popover-foreground shadow-md ring-1 ring-border/80"
                >
                    {links}
                </div>
            ) : null}
        </div>
    );
}

function NavLinks({
    className,
    onNavigate,
    variant = 'bar',
}: {
    className?: string;
    onNavigate?: () => void;
    variant?: NavLinksVariant;
}) {
    const { url, props } = usePage();
    const currentPath = url.split('?')[0] || '/';
    const navItems = props.navigationMenu ?? [];
    const genres = props.taxonomyNav?.categories ?? [];
    const languages = props.taxonomyNav?.languages ?? [];
    const [openKey, setOpenKey] = useState<string | null>(null);
    const navRef = useRef<HTMLElement>(null);

    useEffect(() => {
        if (variant === 'sheet' || openKey === null) {
            return;
        }

        const closeOnOutsidePointer = (event: PointerEvent) => {
            if (
                navRef.current &&
                !navRef.current.contains(event.target as Node)
            ) {
                setOpenKey(null);
            }
        };

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpenKey(null);
            }
        };

        document.addEventListener('pointerdown', closeOnOutsidePointer);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOnOutsidePointer);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [openKey, variant]);

    const flyouts: ReactNode[] = [];

    if (genres.length > 0 && !navHasPathPrefix(navItems, '/resources/genre')) {
        flyouts.push(
            <TaxonomyFlyout
                key="genres-nav"
                menuKey="genres"
                label="Genres"
                icon={Library}
                items={genres}
                hrefFor={(item) => resourcesGenre.url(item.value)}
                currentPath={currentPath}
                activePrefix="/resources/genre"
                variant={variant}
                openKey={openKey}
                onOpenKeyChange={setOpenKey}
                onNavigate={onNavigate}
            />,
        );
    }

    if (
        languages.length > 0 &&
        !navHasPathPrefix(navItems, '/resources/language')
    ) {
        flyouts.push(
            <TaxonomyFlyout
                key="languages-nav"
                menuKey="languages"
                label="Languages"
                icon={Languages}
                items={languages}
                hrefFor={(item) => resourcesLanguage.url(item.value)}
                currentPath={currentPath}
                activePrefix="/resources/language"
                variant={variant}
                openKey={openKey}
                onOpenKeyChange={setOpenKey}
                onNavigate={onNavigate}
            />,
        );
    }

    const insertAt = taxonomyFlyoutInsertIndex(navItems);
    const nodes: ReactNode[] = [];

    navItems.forEach((item, index) => {
        if (index === insertAt) {
            nodes.push(...flyouts);
        }

        const Icon = item.icon ? navigationIcons[item.icon] : undefined;
        const active = isNavigationItemActive(item, navItems, currentPath);
        const external = isExternalMenuUrl(item.url) || item.openInNewTab;
        const content = (
            <>
                {Icon ? <Icon className="size-3.5 shrink-0" /> : null}
                {item.label}
            </>
        );
        const itemClassName = cn(
            navLinkClassName,
            active && navLinkActiveClassName,
        );

        if (external) {
            nodes.push(
                <a
                    key={`${item.label}-${item.url}`}
                    href={item.url}
                    className={itemClassName}
                    onClick={onNavigate}
                    target={item.openInNewTab ? '_blank' : undefined}
                    rel={item.openInNewTab ? 'noopener noreferrer' : undefined}
                    aria-current={active ? 'page' : undefined}
                >
                    {content}
                </a>,
            );

            return;
        }

        nodes.push(
            <Link
                key={`${item.label}-${item.url}`}
                href={item.url}
                className={itemClassName}
                onClick={onNavigate}
                aria-current={active ? 'page' : undefined}
                prefetch={item.match === 'none' ? false : undefined}
            >
                {content}
            </Link>,
        );
    });

    if (insertAt >= navItems.length) {
        nodes.push(...flyouts);
    }

    return (
        <nav
            ref={navRef}
            className={cn('flex items-center gap-1.5', className)}
        >
            {nodes}
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
                    aria-pressed={isDark}
                >
                    {/*
                      Show the *current* mode icon (Sun = light, Moon = dark),
                      matching appearance settings and avoiding inverted affordance.
                    */}
                    {isDark ? (
                        <Moon className="size-4 shrink-0" strokeWidth={1.75} />
                    ) : (
                        <Sun className="size-4 shrink-0" strokeWidth={1.75} />
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
                        fallbackClassName="rounded-full bg-accent text-[11px] text-accent-foreground"
                    />
                    <span className="hidden max-w-28 truncate text-sm leading-none font-medium text-foreground md:inline">
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
                            <Menu
                                className="size-4 shrink-0"
                                strokeWidth={1.75}
                            />
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
                                className="inline-flex h-8 min-w-0 items-center transition-opacity hover:opacity-80"
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

                        <div className="flex min-h-0 flex-1 flex-col overflow-y-auto px-3 py-4">
                            <NavLinks
                                variant="sheet"
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

                <div className="flex min-w-0 flex-1 items-center gap-4 md:gap-6">
                    <Link
                        href={home()}
                        className="inline-flex h-8 shrink-0 items-center transition-opacity hover:opacity-80"
                    >
                        <SiteLogo />
                    </Link>

                    <div className="hidden min-w-0 items-center gap-1 md:flex">
                        <NavLinks />
                    </div>
                </div>

                <div className="flex items-center gap-2 sm:gap-2.5">
                    <div className="flex items-center gap-0.5 sm:gap-1">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Link
                                    href={search()}
                                    className={iconButtonClassName}
                                    aria-label="Search resources"
                                    prefetch
                                >
                                    <Search
                                        className="size-4 shrink-0"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent side="bottom" sideOffset={4}>
                                Search resources
                            </TooltipContent>
                        </Tooltip>

                        <ThemeToggle />

                        {auth.user ? <NotificationBell /> : null}
                    </div>

                    {auth.user ? (
                        <UserAvatarMenu user={auth.user} />
                    ) : (
                        <div className="hidden items-center gap-2 md:flex">
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
