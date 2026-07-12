import { UserAvatar } from '@/components/user-avatar';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    return (
        <>
            <UserAvatar
                user={user}
                className="h-8 w-8"
                fallbackClassName="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
            />
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
