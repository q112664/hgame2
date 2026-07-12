import { Form, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export function ProfileAvatarForm() {
    const { auth } = usePage<PageProps>().props;
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const avatarUser = {
        name: auth.user.name,
        avatar: previewUrl ?? auth.user.avatar,
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Avatar"
                description="Upload a square image up to 2 MB (JPG, PNG, or WebP)"
            />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <UserAvatar
                    key={avatarUser.avatar ?? 'empty'}
                    user={avatarUser}
                    className="size-20"
                    fallbackClassName="rounded-full bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white"
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Form
                        {...ProfileController.updateAvatar.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="contents"
                        onSuccess={() => {
                            setPreviewUrl(null);

                            if (inputRef.current) {
                                inputRef.current.value = '';
                            }
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    ref={inputRef}
                                    id="avatar"
                                    type="file"
                                    name="avatar"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="sr-only"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];

                                        if (!file) {
                                            setPreviewUrl(null);

                                            return;
                                        }

                                        setPreviewUrl(URL.createObjectURL(file));
                                        event.currentTarget.form?.requestSubmit();
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    data-test="upload-avatar-button"
                                    onClick={() => inputRef.current?.click()}
                                >
                                    <Camera data-icon="inline-start" />
                                    {auth.user.avatar
                                        ? 'Change photo'
                                        : 'Upload photo'}
                                </Button>
                                <InputError
                                    className="basis-full"
                                    message={errors.avatar}
                                />
                            </>
                        )}
                    </Form>

                    {auth.user.avatar ? (
                        <Form
                            {...ProfileController.destroyAvatar.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setPreviewUrl(null)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    disabled={processing}
                                    data-test="remove-avatar-button"
                                >
                                    <Trash2 data-icon="inline-start" />
                                    Remove
                                </Button>
                            )}
                        </Form>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
