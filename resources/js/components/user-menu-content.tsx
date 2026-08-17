import { Link, router } from '@inertiajs/react';
import { Heart, LayoutDashboard, LogOut, Settings, UserRound } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/settings';
import { favorites, show as userShow } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const isAdmin = Boolean(user.is_admin);

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={userShow(user.slug)}
                        prefetch
                        onClick={cleanup}
                    >
                        <UserRound className="mr-2" />
                        Profile
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={favorites(user.slug)}
                        prefetch
                        onClick={cleanup}
                    >
                        <Heart className="mr-2" />
                        Favorites
                    </Link>
                </DropdownMenuItem>
                {isAdmin ? (
                    <DropdownMenuItem asChild>
                        <a
                            className="block w-full cursor-pointer"
                            href="/admin"
                            onClick={cleanup}
                        >
                            <LayoutDashboard className="mr-2" />
                            Admin panel
                        </a>
                    </DropdownMenuItem>
                ) : null}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
