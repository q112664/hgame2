import { useState } from 'react';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

type Props = {
    user: Pick<User, 'name' | 'avatar'>;
    className?: string;
    fallbackClassName?: string;
};

/**
 * Hydration-safe avatar. Radix AvatarImage/Fallback swap on load status and
 * mismatches SSR HTML (img vs initials span).
 */
export function UserAvatar({ user, className, fallbackClassName }: Props) {
    const getInitials = useInitials();
    const [failed, setFailed] = useState(false);
    const showImage = Boolean(user.avatar) && !failed;

    return (
        <span
            data-slot="avatar"
            className={cn(
                'relative flex size-8 shrink-0 overflow-hidden rounded-full select-none',
                className,
            )}
        >
            {showImage ? (
                <img
                    data-slot="avatar-image"
                    src={user.avatar ?? undefined}
                    alt={user.name}
                    className="aspect-square size-full object-cover"
                    onError={() => setFailed(true)}
                />
            ) : (
                <span
                    data-slot="avatar-fallback"
                    className={cn(
                        'flex size-full items-center justify-center rounded-full bg-muted text-sm text-muted-foreground',
                        fallbackClassName,
                    )}
                >
                    {getInitials(user.name)}
                </span>
            )}
        </span>
    );
}
