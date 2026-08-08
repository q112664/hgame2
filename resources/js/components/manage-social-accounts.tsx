import { router, usePage } from '@inertiajs/react';
import { Link2, Unlink } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    redirect as linkRedirect,
    unlink as socialUnlink,
} from '@/routes/security/social';
import type { SocialConnection, SocialProvider } from '@/types/auth';

export type Props = {
    socialConnections?: SocialConnection[];
};

const providerLabels: Record<SocialProvider, string> = {
    google: 'Google',
    discord: 'Discord',
};

export default function ManageSocialAccounts({
    socialConnections = [],
}: Props) {
    const { errors, status } = usePage().props as {
        errors: Record<string, string>;
        status?: string;
    };

    if (socialConnections.length === 0) {
        return null;
    }

    const handleUnlink = (provider: SocialProvider) => {
        if (
            !window.confirm(`Unlink your ${providerLabels[provider]} account?`)
        ) {
            return;
        }

        router.delete(socialUnlink.url(provider), {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Connected accounts"
                description="Link Google or Discord for faster sign-in"
            />

            {status ? (
                <p className="text-sm font-medium text-success">{status}</p>
            ) : null}
            <InputError message={errors.social} />

            <div className="divide-y divide-border overflow-hidden rounded-md border border-border">
                {socialConnections.map((connection) => (
                    <div
                        key={connection.provider}
                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        data-test={`social-connection-${connection.provider}`}
                    >
                        <div className="min-w-0 space-y-1">
                            <div className="flex items-center gap-2">
                                <p className="font-medium text-foreground">
                                    {connection.label}
                                </p>
                                {connection.linked ? (
                                    <span className="rounded-full bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
                                        Linked
                                    </span>
                                ) : (
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                        Not linked
                                    </span>
                                )}
                            </div>
                            {connection.linked && connection.email ? (
                                <p className="truncate text-sm text-muted-foreground">
                                    {connection.email}
                                </p>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {connection.available
                                        ? `Sign in with ${connection.label}`
                                        : `${connection.label} login is currently disabled`}
                                </p>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-2">
                            {connection.linked ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!connection.canUnlink}
                                    title={
                                        connection.canUnlink
                                            ? undefined
                                            : 'Add a password or another sign-in method first'
                                    }
                                    onClick={() =>
                                        handleUnlink(connection.provider)
                                    }
                                    data-test={`social-unlink-${connection.provider}`}
                                >
                                    <Unlink className="size-4" />
                                    Unlink
                                </Button>
                            ) : connection.available ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a
                                        href={
                                            linkRedirect(connection.provider)
                                                .url
                                        }
                                        data-test={`social-link-${connection.provider}`}
                                    >
                                        <Link2 className="size-4" />
                                        Link
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
