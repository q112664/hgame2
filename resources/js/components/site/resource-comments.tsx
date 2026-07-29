import { router, usePage } from '@inertiajs/react';
import { CornerDownRight, MessageSquare, Pencil, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { UserAvatar } from '@/components/user-avatar';
import {
    formatAbsoluteDateTime,
    formatRelativeTime,
} from '@/lib/datetime';
import { cn } from '@/lib/utils';

export type ResourceCommentUser = {
    id: number;
    name: string;
    avatar: string | null;
};

export type ResourceCommentReply = {
    id: number;
    body: string;
    createdAt: string | null;
    updatedAt?: string | null;
    isEdited?: boolean;
    isMine?: boolean;
    canEdit?: boolean;
    canDelete: boolean;
    replyTo: { id: number; name: string } | null;
    user: ResourceCommentUser;
};

export type ResourceComment = ResourceCommentReply & {
    replies?: ResourceCommentReply[];
};

type Props = {
    resourceId: string;
    comments: ResourceComment[];
};

const MAX_LENGTH = 2000;

type ReplyTarget = {
    /** Comment id used as parent_id (may be a root or nested reply). */
    commentId: number;
    userName: string;
};

function mentionPrefix(name: string): string {
    return `@${name} `;
}

function CommentBody({
    body,
    nested = false,
}: {
    body: string;
    nested?: boolean;
}) {
    const match = body.match(/^@([^\s@]+(?:\s+[^\s@]+){0,3})\s([\s\S]*)$/);
    const textClass = cn(
        'mt-1 whitespace-pre-wrap leading-relaxed text-foreground/90',
        nested ? 'text-[13px] sm:text-sm' : 'text-sm',
    );

    if (!match) {
        return <p className={textClass}>{body}</p>;
    }

    return (
        <p className={textClass}>
            <span className="rounded-sm bg-primary/10 px-1 py-0.5 font-medium text-primary dark:bg-primary/15">
                @{match[1]}
            </span>
            {match[2] !== '' ? ` ${match[2]}` : null}
        </p>
    );
}

function CommentActionButton({
    onClick,
    disabled,
    destructive = false,
    children,
}: {
    onClick: () => void;
    disabled?: boolean;
    destructive?: boolean;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex h-7 items-center gap-1 rounded-md px-1.5 text-xs font-medium transition-colors',
                'text-muted-foreground hover:bg-muted hover:text-foreground',
                'disabled:pointer-events-none disabled:opacity-50',
                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                destructive && 'hover:bg-destructive/10 hover:text-destructive',
            )}
        >
            {children}
        </button>
    );
}

export function ResourceComments({ resourceId, comments }: Props) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();
    const authUser = page.props.auth.user;
    const isAuthenticated = Boolean(authUser);
    const [body, setBody] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editBody, setEditBody] = useState('');
    const [isSavingEdit, setIsSavingEdit] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [editError, setEditError] = useState<string | null>(null);
    const [replyTarget, setReplyTarget] = useState<ReplyTarget | null>(null);
    const listTopRef = useRef<HTMLDivElement>(null);
    const composerRef = useRef<HTMLTextAreaElement>(null);
    const shouldScrollToTop = useRef(false);

    const totalCount = useMemo(() => {
        return comments.reduce(
            (sum, comment) => sum + 1 + (comment.replies?.length ?? 0),
            0,
        );
    }, [comments]);

    useEffect(() => {
        if (!shouldScrollToTop.current) {
            return;
        }

        shouldScrollToTop.current = false;
        listTopRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });
    }, [comments]);

    const requireAuth = (): boolean => {
        if (isAuthenticated) {
            return true;
        }

        openAuthDialog('login', { redirect: page.url });

        return false;
    };

    const beginReply = (comment: ResourceCommentReply) => {
        if (!requireAuth()) {
            return;
        }

        setReplyTarget({
            commentId: comment.id,
            userName: comment.user.name,
        });
        setBody(mentionPrefix(comment.user.name));
        setError(null);
        setEditingId(null);

        requestAnimationFrame(() => {
            composerRef.current?.focus();
            const el = composerRef.current;

            if (el) {
                const len = el.value.length;
                el.setSelectionRange(len, len);
            }
        });
    };

    const cancelReply = () => {
        setReplyTarget(null);
        setBody('');
        setError(null);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!requireAuth()) {
            return;
        }

        const trimmed = body.trim();

        if (trimmed === '') {
            setError('Please write a comment.');

            return;
        }

        setError(null);
        setIsSubmitting(true);

        if (!replyTarget) {
            shouldScrollToTop.current = true;
        }

        router.post(
            `/resources/${resourceId}/comments`,
            {
                body: trimmed,
                parent_id: replyTarget?.commentId ?? null,
            },
            {
                preserveScroll: true,
                only: ['comments', 'commentsCount'],
                onSuccess: () => {
                    setBody('');
                    setReplyTarget(null);
                },
                onError: (errors) => {
                    shouldScrollToTop.current = false;
                    setError(
                        typeof errors.body === 'string'
                            ? errors.body
                            : typeof errors.parent_id === 'string'
                              ? errors.parent_id
                              : 'Could not post comment.',
                    );
                },
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const startEdit = (comment: ResourceCommentReply) => {
        setEditingId(comment.id);
        setEditBody(comment.body);
        setEditError(null);
        setReplyTarget(null);
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditBody('');
        setEditError(null);
    };

    const saveEdit = (commentId: number) => {
        if (!requireAuth()) {
            return;
        }

        const trimmed = editBody.trim();

        if (trimmed === '') {
            setEditError('Comment cannot be empty.');

            return;
        }

        setEditError(null);
        setIsSavingEdit(true);

        router.patch(
            `/resources/${resourceId}/comments/${commentId}`,
            { body: trimmed },
            {
                preserveScroll: true,
                only: ['comments', 'commentsCount'],
                onSuccess: () => cancelEdit(),
                onError: (errors) => {
                    setEditError(
                        typeof errors.body === 'string'
                            ? errors.body
                            : 'Could not update comment.',
                    );
                },
                onFinish: () => setIsSavingEdit(false),
            },
        );
    };

    const remove = (commentId: number) => {
        if (!requireAuth()) {
            return;
        }

        if (
            !window.confirm(
                'Delete this comment? Replies under it will also be removed.',
            )
        ) {
            return;
        }

        setDeletingId(commentId);

        router.delete(`/resources/${resourceId}/comments/${commentId}`, {
            preserveScroll: true,
            only: ['comments', 'commentsCount'],
            onFinish: () => setDeletingId(null),
        });
    };

    const remaining = MAX_LENGTH - body.length;
    const nearLimit = remaining <= 100;

    const renderComment = (
        comment: ResourceCommentReply,
        options: { nested?: boolean } = {},
    ) => {
        const isEditing = editingId === comment.id;
        const nested = options.nested ?? false;

        return (
            <article
                key={comment.id}
                className={cn(
                    'group/comment flex gap-2.5 sm:gap-3',
                    nested && 'min-w-0',
                )}
            >
                <UserAvatar
                    user={comment.user}
                    className={cn(
                        'mt-0.5 shrink-0 ring-1 ring-border/60',
                        nested ? 'size-7 sm:size-8' : 'size-9',
                    )}
                    fallbackClassName="rounded-full bg-muted text-[10px] text-muted-foreground sm:text-xs"
                />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                        <span
                            className={cn(
                                'font-medium text-foreground',
                                nested ? 'text-[13px] sm:text-sm' : 'text-sm',
                            )}
                        >
                            {comment.user.name}
                        </span>
                        {comment.isMine ? (
                            <Badge
                                variant="secondary"
                                className="h-5 px-1.5 text-[10px] font-medium leading-none"
                            >
                                You
                            </Badge>
                        ) : null}
                        {comment.replyTo ? (
                            <span className="inline-flex items-center gap-0.5 text-[11px] text-muted-foreground sm:text-xs">
                                <CornerDownRight className="size-2.5 opacity-70" />
                                <span className="font-medium text-foreground/70">
                                    @{comment.replyTo.name}
                                </span>
                            </span>
                        ) : null}
                        {comment.createdAt ? (
                            <>
                                <span
                                    className="text-[11px] text-muted-foreground/50 sm:text-xs"
                                    aria-hidden
                                >
                                    ·
                                </span>
                                <time
                                    dateTime={comment.createdAt}
                                    title={formatAbsoluteDateTime(
                                        comment.createdAt,
                                    )}
                                    className="text-[11px] text-muted-foreground tabular-nums sm:text-xs"
                                >
                                    {formatRelativeTime(comment.createdAt)}
                                </time>
                            </>
                        ) : null}
                        {comment.isEdited ? (
                            <span className="text-[11px] text-muted-foreground/70 sm:text-xs">
                                · edited
                            </span>
                        ) : null}
                    </div>

                    {isEditing ? (
                        <div className="mt-2 flex flex-col gap-2">
                            <Textarea
                                value={editBody}
                                onChange={(event) => {
                                    setEditBody(event.target.value);

                                    if (editError) {
                                        setEditError(null);
                                    }
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        event.preventDefault();
                                        cancelEdit();

                                        return;
                                    }

                                    if (
                                        (event.metaKey || event.ctrlKey) &&
                                        event.key === 'Enter'
                                    ) {
                                        event.preventDefault();
                                        saveEdit(comment.id);
                                    }
                                }}
                                rows={3}
                                maxLength={MAX_LENGTH}
                                disabled={isSavingEdit}
                                className="min-h-[4.5rem] resize-y bg-background text-sm"
                                autoFocus
                            />
                            {editError ? (
                                <p
                                    className="text-xs text-destructive"
                                    role="alert"
                                >
                                    {editError}
                                </p>
                            ) : null}
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={
                                        isSavingEdit || editBody.trim() === ''
                                    }
                                    onClick={() => saveEdit(comment.id)}
                                >
                                    {isSavingEdit ? 'Saving…' : 'Save'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={isSavingEdit}
                                    onClick={cancelEdit}
                                >
                                    Cancel
                                </Button>
                                <span className="text-[11px] text-muted-foreground">
                                    Esc to cancel · Ctrl/⌘+Enter to save
                                </span>
                            </div>
                        </div>
                    ) : (
                        <>
                            <CommentBody body={comment.body} nested={nested} />
                            <div
                                className={cn(
                                    'mt-1 flex flex-wrap items-center gap-0.5',
                                    'opacity-80 transition-opacity group-hover/comment:opacity-100 sm:opacity-70',
                                )}
                            >
                                <CommentActionButton
                                    onClick={() => beginReply(comment)}
                                >
                                    <CornerDownRight className="size-3.5" />
                                    Reply
                                </CommentActionButton>
                                {comment.canEdit ? (
                                    <CommentActionButton
                                        onClick={() => startEdit(comment)}
                                    >
                                        <Pencil className="size-3.5" />
                                        Edit
                                    </CommentActionButton>
                                ) : null}
                                {comment.canDelete ? (
                                    <CommentActionButton
                                        destructive
                                        disabled={deletingId === comment.id}
                                        onClick={() => remove(comment.id)}
                                    >
                                        <Trash2 className="size-3.5" />
                                        {deletingId === comment.id
                                            ? 'Deleting…'
                                            : 'Delete'}
                                    </CommentActionButton>
                                ) : null}
                            </div>
                        </>
                    )}
                </div>
            </article>
        );
    };

    return (
        <section
            aria-label="Comments"
            className="overflow-hidden rounded-lg border border-border bg-card"
        >
            <header className="flex items-center justify-between gap-3 border-b border-border/80 bg-muted/30 px-4 py-3 sm:px-5 dark:bg-muted/20">
                <div className="flex items-center gap-2">
                    <span className="flex size-7 items-center justify-center rounded-md bg-background text-muted-foreground shadow-sm ring-1 ring-border/60">
                        <MessageSquare className="size-3.5" aria-hidden />
                    </span>
                    <h2 className="font-heading text-sm font-semibold tracking-tight text-foreground">
                        Comments
                    </h2>
                    {totalCount > 0 ? (
                        <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium tabular-nums text-muted-foreground">
                            {totalCount}
                        </span>
                    ) : null}
                </div>
            </header>

            <div className="flex flex-col gap-0">
                {/* Composer */}
                <div className="border-b border-border/70 px-4 py-4 sm:px-5">
                    {!isAuthenticated ? (
                        <div
                            role="region"
                            aria-label="Sign in to comment"
                            className={cn(
                                'flex min-h-[5.5rem] flex-col items-center justify-center gap-2.5 rounded-md border border-dashed border-border/90',
                                'bg-muted/20 px-4 py-4 text-center dark:bg-muted/10',
                            )}
                        >
                            <p className="text-sm text-muted-foreground">
                                Log in to leave a comment
                            </p>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() =>
                                        openAuthDialog('login', {
                                            redirect: page.url,
                                        })
                                    }
                                >
                                    Log in
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        openAuthDialog('register', {
                                            redirect: page.url,
                                        })
                                    }
                                >
                                    Sign up
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <form onSubmit={submit}>
                            <div className="flex gap-2.5 sm:gap-3">
                                {authUser ? (
                                    <UserAvatar
                                        user={authUser}
                                        className="mt-0.5 size-9 shrink-0 ring-1 ring-border/60"
                                        fallbackClassName="rounded-full bg-muted text-xs text-muted-foreground"
                                    />
                                ) : null}

                                <div className="flex min-w-0 flex-1 flex-col gap-2">
                                    {replyTarget ? (
                                        <div className="flex items-center justify-between gap-2 rounded-md border border-primary/20 bg-primary/8 px-2.5 py-1.5 text-xs dark:bg-primary/12">
                                            <span className="inline-flex min-w-0 items-center gap-1.5 text-foreground">
                                                <CornerDownRight className="size-3.5 shrink-0 text-primary" />
                                                <span className="truncate">
                                                    Replying to{' '}
                                                    <span className="font-semibold text-primary">
                                                        @{replyTarget.userName}
                                                    </span>
                                                </span>
                                            </span>
                                            <button
                                                type="button"
                                                className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-background/80 hover:text-foreground"
                                                aria-label="Cancel reply"
                                                onClick={cancelReply}
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </div>
                                    ) : null}

                                    <label
                                        className="sr-only"
                                        htmlFor="resource-comment-body"
                                    >
                                        Write a comment
                                    </label>
                                    <Textarea
                                        ref={composerRef}
                                        id="resource-comment-body"
                                        value={body}
                                        onChange={(event) => {
                                            setBody(event.target.value);

                                            if (error) {
                                                setError(null);
                                            }
                                        }}
                                        onKeyDown={(event) => {
                                            if (
                                                (event.metaKey ||
                                                    event.ctrlKey) &&
                                                event.key === 'Enter'
                                            ) {
                                                event.preventDefault();
                                                event.currentTarget.form?.requestSubmit();
                                            }
                                        }}
                                        placeholder={
                                            replyTarget
                                                ? `Reply to @${replyTarget.userName}…`
                                                : 'Share your thoughts…'
                                        }
                                        rows={3}
                                        maxLength={MAX_LENGTH}
                                        disabled={isSubmitting}
                                        className={cn(
                                            'min-h-[4.75rem] resize-y bg-muted/25 text-sm shadow-none dark:bg-muted/15',
                                            'focus-visible:bg-background',
                                            error &&
                                                'border-destructive focus-visible:border-destructive',
                                        )}
                                    />
                                    {error ? (
                                        <p
                                            className="text-xs text-destructive"
                                            role="alert"
                                        >
                                            {error}
                                        </p>
                                    ) : null}
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-[11px] text-muted-foreground sm:text-xs">
                                            <span
                                                className={cn(
                                                    'tabular-nums',
                                                    nearLimit &&
                                                        'font-medium text-amber-600 dark:text-amber-400',
                                                    remaining <= 0 &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {body.length > 0
                                                    ? `${body.length}/${MAX_LENGTH}`
                                                    : 'Ctrl/⌘ + Enter to post'}
                                            </span>
                                        </p>
                                        <div className="flex items-center gap-1.5">
                                            {replyTarget ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={isSubmitting}
                                                    onClick={cancelReply}
                                                >
                                                    Cancel
                                                </Button>
                                            ) : null}
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={
                                                    isSubmitting ||
                                                    body.trim() === ''
                                                }
                                            >
                                                {isSubmitting
                                                    ? 'Posting…'
                                                    : replyTarget
                                                      ? 'Post reply'
                                                      : 'Post'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    )}
                </div>

                <div ref={listTopRef} />

                {comments.length === 0 ? (
                    <SiteEmptyState
                        icon={MessageSquare}
                        title="No comments yet"
                        description="Be the first to share thoughts — reply and @mention others."
                        className="min-h-0 rounded-none border-0 bg-transparent py-10"
                    />
                ) : (
                    <ul className="divide-y divide-border/60">
                        {comments.map((comment) => (
                            <li
                                key={comment.id}
                                className="flex flex-col gap-3 px-4 py-4 sm:gap-3.5 sm:px-5 sm:py-5"
                            >
                                {renderComment(comment)}
                                {comment.replies &&
                                comment.replies.length > 0 ? (
                                    <div className="flex gap-2.5 sm:gap-3">
                                        <div
                                            className="w-9 shrink-0"
                                            aria-hidden
                                        />
                                        <div className="relative min-w-0 flex-1">
                                            <span
                                                aria-hidden
                                                className="pointer-events-none absolute bottom-1 left-0 top-0.5 w-px rounded-full bg-border/80 sm:w-0.5 dark:bg-border/60"
                                            />
                                            <ul
                                                className="flex flex-col gap-3 pl-3 sm:gap-3.5 sm:pl-3.5"
                                                aria-label="Replies"
                                            >
                                                {comment.replies.map(
                                                    (reply) => (
                                                        <li key={reply.id}>
                                                            {renderComment(
                                                                reply,
                                                                {
                                                                    nested: true,
                                                                },
                                                            )}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    </div>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
