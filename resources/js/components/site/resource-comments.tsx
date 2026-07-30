import { router, usePage } from '@inertiajs/react';
import {
    CornerDownRight,
    MessageSquare,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    destroy as destroyComment,
    store as storeComment,
    update as updateComment,
} from '@/actions/App/Http/Controllers/GameCommentController';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { UserAvatar } from '@/components/user-avatar';
import { formatAbsoluteDateTime, formatRelativeTime } from '@/lib/datetime';
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

type PendingScroll = number;

export function commentDomId(id: number): string {
    return `comment-${id}`;
}

/** Drop a legacy leading @Name prefix so reply bodies stay plain text. */
function stripLeadingMention(body: string): string {
    return body.replace(/^@[^\s@]+(?:\s+[^\s@]+){0,3}\s+/, '').trimStart();
}

function scrollToCommentElement(
    id: number,
    behavior: ScrollBehavior = 'smooth',
): boolean {
    const el = document.getElementById(commentDomId(id));

    if (!el) {
        return false;
    }

    el.scrollIntoView({ behavior, block: 'center' });

    return true;
}

function CommentBody({
    body,
    nested = false,
}: {
    body: string;
    nested?: boolean;
}) {
    const text = stripLeadingMention(body);

    return (
        <p
            className={cn(
                'mt-1 leading-relaxed whitespace-pre-wrap text-foreground/90',
                nested ? 'text-[13px] sm:text-sm' : 'text-sm',
            )}
        >
            {text}
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
                'inline-flex h-8 items-center gap-1 rounded-md px-1.5 text-[11px] font-medium transition-colors sm:h-7 sm:text-xs',
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
    const [highlightedId, setHighlightedId] = useState<number | null>(null);
    const composerRef = useRef<HTMLTextAreaElement>(null);
    const pendingScrollRef = useRef<PendingScroll | null>(null);
    const highlightTimerRef = useRef<number | null>(null);

    const totalCount = useMemo(() => {
        return comments.reduce(
            (sum, comment) => sum + 1 + (comment.replies?.length ?? 0),
            0,
        );
    }, [comments]);

    const flashHighlight = (id: number) => {
        setHighlightedId(id);

        if (highlightTimerRef.current !== null) {
            window.clearTimeout(highlightTimerRef.current);
        }

        highlightTimerRef.current = window.setTimeout(() => {
            setHighlightedId((current) => (current === id ? null : current));
            highlightTimerRef.current = null;
        }, 2400);
    };

    const focusComment = (id: number, behavior: ScrollBehavior = 'smooth') => {
        if (!scrollToCommentElement(id, behavior)) {
            return false;
        }

        flashHighlight(id);

        return true;
    };

    useEffect(() => {
        const pendingId = pendingScrollRef.current;

        if (pendingId === null) {
            return;
        }

        let retryTimer: number | null = null;

        const run = (behavior: ScrollBehavior = 'smooth') => {
            if (focusComment(pendingId, behavior)) {
                pendingScrollRef.current = null;

                return true;
            }

            return false;
        };

        // Wait a frame so the comments list is painted after Inertia updates.
        const frame = window.requestAnimationFrame(() => {
            if (run('smooth')) {
                return;
            }

            // New comments can land before the list is fully laid out.
            retryTimer = window.setTimeout(() => {
                run('auto');
            }, 120);
        });

        return () => {
            window.cancelAnimationFrame(frame);

            if (retryTimer !== null) {
                window.clearTimeout(retryTimer);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-run when comments change
    }, [comments]);

    useEffect(() => {
        return () => {
            if (highlightTimerRef.current !== null) {
                window.clearTimeout(highlightTimerRef.current);
            }
        };
    }, []);

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
        setBody('');
        setError(null);
        setEditingId(null);

        requestAnimationFrame(() => {
            composerRef.current?.focus();
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

        const trimmed = stripLeadingMention(body.trim());

        if (trimmed === '') {
            setError('Please write a comment.');

            return;
        }

        setError(null);
        setIsSubmitting(true);

        const parentId = replyTarget?.commentId ?? null;

        router.post(
            storeComment(resourceId).url,
            {
                body: trimmed,
                parent_id: parentId,
            },
            {
                preserveScroll: true,
                only: ['comments', 'commentsCount'],
                onSuccess: () => {
                    setBody('');
                    setReplyTarget(null);
                },
                onFlash: (flash) => {
                    const createdCommentId = flash.createdCommentId;

                    if (
                        typeof createdCommentId === 'number' &&
                        Number.isInteger(createdCommentId) &&
                        createdCommentId > 0
                    ) {
                        pendingScrollRef.current = createdCommentId;
                    }
                },
                onError: (errors) => {
                    pendingScrollRef.current = null;
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

        const trimmed = stripLeadingMention(editBody.trim());

        if (trimmed === '') {
            setEditError('Comment cannot be empty.');

            return;
        }

        setEditError(null);
        setIsSavingEdit(true);

        router.patch(
            updateComment({ resource: resourceId, comment: commentId }).url,
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

        router.delete(
            destroyComment({ resource: resourceId, comment: commentId }).url,
            {
                preserveScroll: true,
                only: ['comments', 'commentsCount'],
                onFinish: () => setDeletingId(null),
            },
        );
    };

    const remaining = MAX_LENGTH - body.length;
    const nearLimit = remaining <= 100;

    const renderComment = (
        comment: ResourceCommentReply,
        options: { nested?: boolean; rootAuthorId?: number } = {},
    ) => {
        const isEditing = editingId === comment.id;
        const nested = options.nested ?? false;
        const rootAuthorId = options.rootAuthorId;

        const isHighlighted = highlightedId === comment.id;

        // Indent + reply rail already show this is a reply. Only label when
        // answering someone other than the thread root author.
        const showReplyTo =
            nested &&
            comment.replyTo !== null &&
            (rootAuthorId === undefined || comment.replyTo.id !== rootAuthorId);

        return (
            <article
                key={comment.id}
                id={commentDomId(comment.id)}
                data-comment-id={comment.id}
                className={cn(
                    'group/comment flex scroll-mt-24 gap-2.5 rounded-md sm:gap-3',
                    nested && 'min-w-0',
                    'target:bg-primary/6 target:ring-2 target:ring-primary/25 target:ring-offset-1 target:ring-offset-card',
                    isHighlighted &&
                        'bg-primary/6 ring-2 ring-primary/25 ring-offset-1 ring-offset-card',
                )}
            >
                <UserAvatar
                    user={comment.user}
                    className={cn(
                        'mt-0.5 shrink-0 ring-1 ring-border/60',
                        nested ? 'size-7 sm:size-8' : 'size-8 sm:size-9',
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
                                className="h-5 px-1.5 text-[10px] leading-none font-medium"
                            >
                                You
                            </Badge>
                        ) : null}
                        {showReplyTo && comment.replyTo ? (
                            <span className="min-w-0 text-[11px] leading-none text-muted-foreground sm:text-xs">
                                <span className="text-muted-foreground/60">
                                    →
                                </span>{' '}
                                <span className="truncate font-normal text-muted-foreground">
                                    {comment.replyTo.name}
                                </span>
                            </span>
                        ) : null}
                        {comment.createdAt ? (
                            <>
                                <span
                                    className="text-xs text-muted-foreground/50"
                                    aria-hidden
                                >
                                    ·
                                </span>
                                <time
                                    dateTime={comment.createdAt}
                                    title={formatAbsoluteDateTime(
                                        comment.createdAt,
                                    )}
                                    className="text-xs text-muted-foreground tabular-nums"
                                >
                                    {formatRelativeTime(comment.createdAt)}
                                </time>
                            </>
                        ) : null}
                        {comment.isEdited ? (
                            <span className="text-xs text-muted-foreground/70">
                                · edited
                            </span>
                        ) : null}
                    </div>

                    {isEditing ? (
                        <div className="mt-1.5 flex flex-col gap-1.5">
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
                                rows={2}
                                maxLength={MAX_LENGTH}
                                disabled={isSavingEdit}
                                className="min-h-[3.5rem] resize-y bg-background text-sm"
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
                                        isSavingEdit ||
                                        stripLeadingMention(editBody.trim()) ===
                                            ''
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
                            </div>
                        </div>
                    ) : (
                        <>
                            <CommentBody body={comment.body} nested={nested} />
                            <div
                                className={cn(
                                    'mt-0.5 flex flex-wrap items-center gap-0.5',
                                    'opacity-80 transition-opacity group-hover/comment:opacity-100 sm:opacity-70',
                                )}
                            >
                                <CommentActionButton
                                    onClick={() => beginReply(comment)}
                                >
                                    <CornerDownRight className="size-3" />
                                    Reply
                                </CommentActionButton>
                                {comment.canEdit ? (
                                    <CommentActionButton
                                        onClick={() => startEdit(comment)}
                                    >
                                        <Pencil className="size-3" />
                                        Edit
                                    </CommentActionButton>
                                ) : null}
                                {comment.canDelete ? (
                                    <CommentActionButton
                                        destructive
                                        disabled={deletingId === comment.id}
                                        onClick={() => remove(comment.id)}
                                    >
                                        <Trash2 className="size-3" />
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
            <header className="flex items-center justify-between gap-2 border-b border-border/80 bg-muted/30 px-3 py-2 sm:px-4 dark:bg-muted/20">
                <div className="flex items-center gap-1.5">
                    <span className="flex size-6 items-center justify-center rounded-md bg-background text-muted-foreground ring-1 ring-border/60">
                        <MessageSquare className="size-3.5" aria-hidden />
                    </span>
                    <h2 className="font-heading text-[13px] font-semibold tracking-tight text-foreground sm:text-sm">
                        Comments
                    </h2>
                    {totalCount > 0 ? (
                        <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-muted px-1 text-[10px] font-medium text-muted-foreground tabular-nums">
                            {totalCount}
                        </span>
                    ) : null}
                </div>
            </header>

            <div className="flex flex-col gap-0">
                {/* Composer */}
                <div className="border-b border-border/70 px-3 py-3 sm:px-4">
                    {!isAuthenticated ? (
                        <div
                            role="region"
                            aria-label="Sign in to comment"
                            className={cn(
                                'flex min-h-[4.5rem] flex-col items-center justify-center gap-2 rounded-md border border-dashed border-border/90',
                                'bg-muted/20 px-3 py-3 text-center dark:bg-muted/10',
                            )}
                        >
                            <p className="text-[13px] text-muted-foreground sm:text-sm">
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
                            <div className="flex gap-2">
                                {authUser ? (
                                    <UserAvatar
                                        user={authUser}
                                        className="mt-0.5 size-7 shrink-0 ring-1 ring-border/60 sm:size-8"
                                        fallbackClassName="rounded-full bg-muted text-[10px] text-muted-foreground sm:text-xs"
                                    />
                                ) : null}

                                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                    {replyTarget ? (
                                        <div className="flex items-center justify-between gap-2 rounded-md border border-primary/20 bg-primary/8 px-2.5 py-1.5 text-xs dark:bg-primary/12">
                                            <span className="inline-flex min-w-0 items-center gap-1.5 text-foreground">
                                                <CornerDownRight className="size-3.5 shrink-0 text-primary" />
                                                <span className="truncate">
                                                    Replying to{' '}
                                                    <span className="font-semibold text-primary">
                                                        {replyTarget.userName}
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
                                                ? `Reply to ${replyTarget.userName}…`
                                                : 'Share your thoughts…'
                                        }
                                        rows={2}
                                        maxLength={MAX_LENGTH}
                                        disabled={isSubmitting}
                                        className={cn(
                                            'min-h-[3.25rem] resize-y bg-muted/25 text-[13px] shadow-none sm:text-sm dark:bg-muted/15',
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
                                    <div className="flex flex-wrap items-center justify-between gap-1.5">
                                        <p className="text-[11px] text-muted-foreground">
                                            <span
                                                className={cn(
                                                    'tabular-nums',
                                                    nearLimit &&
                                                        'font-medium text-amber-600 dark:text-amber-400',
                                                    remaining <= 0 &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {body.length}/{MAX_LENGTH}
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

                {comments.length === 0 ? (
                    <SiteEmptyState
                        icon={MessageSquare}
                        title="No comments yet"
                        className="min-h-0 rounded-none border-0 bg-transparent py-6"
                    />
                ) : (
                    <ul className="divide-y divide-border/60">
                        {comments.map((comment) => (
                            <li
                                key={comment.id}
                                className="flex flex-col gap-2 px-3 py-2.5 sm:gap-2.5 sm:px-4 sm:py-3"
                            >
                                {renderComment(comment)}
                                {comment.replies &&
                                comment.replies.length > 0 ? (
                                    <div className="flex gap-2.5 sm:gap-3">
                                        <div
                                            className="w-8 shrink-0 sm:w-9"
                                            aria-hidden
                                        />
                                        <div className="relative min-w-0 flex-1">
                                            <span
                                                aria-hidden
                                                className="pointer-events-none absolute top-0.5 bottom-1 left-0 w-px rounded-full bg-border/80 dark:bg-border/60"
                                            />
                                            <ul
                                                className="flex flex-col gap-2.5 pl-3 sm:gap-3 sm:pl-3.5"
                                                aria-label="Replies"
                                            >
                                                {comment.replies.map(
                                                    (reply) => (
                                                        <li key={reply.id}>
                                                            {renderComment(
                                                                reply,
                                                                {
                                                                    nested: true,
                                                                    rootAuthorId:
                                                                        comment
                                                                            .user
                                                                            .id,
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
