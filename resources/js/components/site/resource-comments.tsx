import { router, usePage } from '@inertiajs/react';
import {
    CornerDownRight,
    MessageSquare,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    destroy as destroyComment,
    store as storeComment,
    update as updateComment,
} from '@/actions/App/Http/Controllers/GameCommentController';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { SitePagination } from '@/components/site/site-pagination';
import type { PaginatedData } from '@/components/site/site-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { UserAvatar } from '@/components/user-avatar';
import { formatAbsoluteDateTime, formatRelativeTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { comments as resourceCommentsRoute } from '@/routes/resources';

export type ResourceCommentUser = {
    id: number;
    name: string;
    avatar: string | null;
    isAdmin: boolean;
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
    comments: PaginatedData<ResourceComment>;
    commentsCount: number;
};

const MAX_LENGTH = 2000;

type ReplyTarget = {
    /** Comment id used as parent_id (may be a root or nested reply). */
    commentId: number;
    userName: string;
};

export function commentDomId(id: number): string {
    return `comment-${id}`;
}

/**
 * Strip only the exact legacy reply prefix. Root comments are never changed.
 */
function normalizeCommentBody(
    body: string,
    replyToName: string | null = null,
): string {
    if (replyToName === null) {
        return body;
    }

    const prefix = `@${replyToName}`;

    return body.startsWith(`${prefix} `)
        ? body.slice(prefix.length).trimStart()
        : body;
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

/** Retry until the new comment node is painted (and after Inertia scroll restore). */
function scheduleScrollToComment(id: number, onFound: () => void): () => void {
    let cancelled = false;
    const timers: number[] = [];
    // preserveScroll restores the old offset after the visit; keep retrying a bit longer.
    const delaysMs = [0, 16, 50, 100, 180, 320, 500, 800, 1200];

    const tryScroll = (attempt: number) => {
        if (cancelled) {
            return;
        }

        const behavior: ScrollBehavior = attempt < 2 ? 'smooth' : 'auto';

        if (scrollToCommentElement(id, behavior)) {
            onFound();

            return;
        }

        const next = attempt + 1;

        if (next >= delaysMs.length) {
            return;
        }

        const wait = delaysMs[next]! - delaysMs[attempt]!;
        timers.push(
            window.setTimeout(
                () => {
                    tryScroll(next);
                },
                Math.max(wait, 0),
            ),
        );
    };

    timers.push(
        window.setTimeout(() => {
            // Double rAF: wait for React commit + browser paint.
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    tryScroll(0);
                });
            });
        }, 0),
    );

    return () => {
        cancelled = true;
        timers.forEach((timer) => window.clearTimeout(timer));
    };
}

function CommentBody({
    body,
    nested = false,
    replyToName = null,
    showReplyToName = false,
}: {
    body: string;
    nested?: boolean;
    /** Used to remove a legacy prefix from the stored reply body. */
    replyToName?: string | null;
    /** Whether to show the reply target in the rendered text. */
    showReplyToName?: boolean;
}) {
    const text = normalizeCommentBody(body, replyToName);

    return (
        <p
            className={cn(
                'mt-1 leading-relaxed whitespace-pre-wrap text-foreground/90',
                nested ? 'text-[13px] sm:text-sm' : 'text-sm',
            )}
        >
            {showReplyToName && replyToName ? (
                <>
                    <span className="font-medium text-primary">
                        @{replyToName}
                    </span>{' '}
                </>
            ) : null}
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

export function ResourceComments({
    resourceId,
    comments,
    commentsCount,
}: Props) {
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
    const [replyBody, setReplyBody] = useState('');
    const [replyError, setReplyError] = useState<string | null>(null);
    const [isSubmittingReply, setIsSubmittingReply] = useState(false);
    const [highlightedId, setHighlightedId] = useState<number | null>(null);
    /** Drives scroll retries after post — state so effects re-run when the id arrives. */
    const [pendingScrollId, setPendingScrollId] = useState<number | null>(null);
    const composerRef = useRef<HTMLTextAreaElement>(null);
    const inlineReplyRef = useRef<HTMLTextAreaElement>(null);
    const highlightTimerRef = useRef<number | null>(null);
    const pendingScrollIdRef = useRef<number | null>(null);
    const cancelPendingScrollRef = useRef<(() => void) | null>(null);

    const totalCount = commentsCount;
    const commentItems = comments.data;

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

    const clearPendingScroll = () => {
        pendingScrollIdRef.current = null;
        setPendingScrollId(null);
        cancelPendingScrollRef.current?.();
        cancelPendingScrollRef.current = null;
    };

    const queueScrollToComment = (id: number) => {
        pendingScrollIdRef.current = id;
        setPendingScrollId(id);
    };

    useEffect(() => {
        if (pendingScrollId === null) {
            return;
        }

        cancelPendingScrollRef.current?.();
        cancelPendingScrollRef.current = scheduleScrollToComment(
            pendingScrollId,
            () => {
                flashHighlight(pendingScrollId);
                clearPendingScroll();
            },
        );

        return () => {
            cancelPendingScrollRef.current?.();
            cancelPendingScrollRef.current = null;
        };
        // Re-run when the list updates so late DOM nodes are still found.
    }, [pendingScrollId, comments]);

    useEffect(() => {
        return () => {
            if (highlightTimerRef.current !== null) {
                window.clearTimeout(highlightTimerRef.current);
            }

            cancelPendingScrollRef.current?.();
        };
    }, []);

    const requireAuth = (): boolean => {
        if (isAuthenticated) {
            return true;
        }

        openAuthDialog('login', { redirect: page.url });

        return false;
    };

    const cancelReply = () => {
        setReplyTarget(null);
        setReplyBody('');
        setReplyError(null);
    };

    const beginReply = (comment: ResourceCommentReply) => {
        if (!requireAuth()) {
            return;
        }

        setEditingId(null);
        setEditBody('');
        setEditError(null);
        setReplyError(null);
        setReplyBody('');
        setReplyTarget({
            commentId: comment.id,
            userName: comment.user.name,
        });

        requestAnimationFrame(() => {
            inlineReplyRef.current?.focus();
        });
    };

    const postComment = ({
        content,
        parentId,
        onStart,
        onDone,
        onFail,
        onPosted,
    }: {
        content: string;
        parentId: number | null;
        onStart: () => void;
        onDone: () => void;
        onFail: (message: string) => void;
        onPosted: () => void;
    }) => {
        const trimmed = content.trim();

        if (trimmed === '') {
            onFail(
                parentId === null
                    ? 'Please write a comment.'
                    : 'Please write a reply.',
            );

            return;
        }

        onStart();

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
                    onPosted();
                },
                onFlash: (flash) => {
                    const createdCommentId = flash.createdCommentId;

                    if (
                        typeof createdCommentId === 'number' &&
                        Number.isInteger(createdCommentId) &&
                        createdCommentId > 0
                    ) {
                        // Queue immediately so retries start even if the list
                        // paints before / after Inertia restores scroll.
                        queueScrollToComment(createdCommentId);
                    }
                },
                onError: (errors) => {
                    clearPendingScroll();
                    onFail(
                        typeof errors.body === 'string'
                            ? errors.body
                            : typeof errors.parent_id === 'string'
                              ? errors.parent_id
                              : parentId === null
                                ? 'Could not post comment.'
                                : 'Could not post reply.',
                    );
                },
                onFinish: () => {
                    onDone();
                    // Restart retries after Inertia finishes (incl. preserveScroll restore).
                    const id = pendingScrollIdRef.current;

                    if (id !== null) {
                        queueScrollToComment(id);
                    }
                },
            },
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!requireAuth()) {
            return;
        }

        // Top composer is always a root comment — replies use the inline form.
        setError(null);

        postComment({
            content: body,
            parentId: null,
            onStart: () => setIsSubmitting(true),
            onDone: () => setIsSubmitting(false),
            onFail: (message) => setError(message),
            onPosted: () => {
                setBody('');
            },
        });
    };

    const submitInlineReply = (event: React.FormEvent) => {
        event.preventDefault();

        if (!requireAuth() || replyTarget === null) {
            return;
        }

        setReplyError(null);

        postComment({
            content: replyBody,
            parentId: replyTarget.commentId,
            onStart: () => setIsSubmittingReply(true),
            onDone: () => setIsSubmittingReply(false),
            onFail: (message) => setReplyError(message),
            onPosted: () => {
                cancelReply();
            },
        });
    };

    const startEdit = (comment: ResourceCommentReply) => {
        setEditingId(comment.id);
        setEditBody(
            normalizeCommentBody(comment.body, comment.replyTo?.name ?? null),
        );
        setEditError(null);
        cancelReply();
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
        const replyTo = nested ? comment.replyTo : null;
        const replyToName = replyTo?.name ?? null;

        // Indent + reply rail already show this is a reply. Only label when
        // answering someone other than the thread root author.
        const showReplyTo =
            replyTo !== null &&
            (rootAuthorId === undefined || replyTo.id !== rootAuthorId);

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
                        {comment.user.isAdmin ? (
                            <Badge
                                variant="outline"
                                className={cn(
                                    'h-5 border-0 px-1.5 text-[10px] leading-none font-medium shadow-none',
                                    'bg-primary/12 text-primary',
                                    'dark:bg-primary/18 dark:text-primary',
                                )}
                            >
                                Admin
                            </Badge>
                        ) : null}
                        {comment.isMine ? (
                            <Badge
                                variant="secondary"
                                className="h-5 px-1.5 text-[10px] leading-none font-medium"
                            >
                                You
                            </Badge>
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
                            </div>
                        </div>
                    ) : (
                        <>
                            <CommentBody
                                body={comment.body}
                                nested={nested}
                                replyToName={replyToName}
                                showReplyToName={showReplyTo}
                            />
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
                            {replyTarget?.commentId === comment.id ? (
                                <form
                                    className="mt-2 flex flex-col gap-1.5 rounded-md border border-border/80 bg-muted/20 p-2 sm:p-2.5 dark:bg-muted/10"
                                    onSubmit={submitInlineReply}
                                >
                                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span className="min-w-0 truncate">
                                            Reply to{' '}
                                            <span className="font-medium text-primary">
                                                @{replyTarget.userName}
                                            </span>
                                        </span>
                                        <button
                                            type="button"
                                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-background/80 hover:text-foreground"
                                            aria-label="Cancel reply"
                                            disabled={isSubmittingReply}
                                            onClick={cancelReply}
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    </div>
                                    <Textarea
                                        ref={inlineReplyRef}
                                        value={replyBody}
                                        onChange={(event) => {
                                            setReplyBody(event.target.value);

                                            if (replyError) {
                                                setReplyError(null);
                                            }
                                        }}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Escape') {
                                                event.preventDefault();
                                                cancelReply();

                                                return;
                                            }

                                            if (
                                                (event.metaKey ||
                                                    event.ctrlKey) &&
                                                event.key === 'Enter'
                                            ) {
                                                event.preventDefault();
                                                event.currentTarget.form?.requestSubmit();
                                            }
                                        }}
                                        placeholder={`Reply to @${replyTarget.userName}…`}
                                        rows={2}
                                        maxLength={MAX_LENGTH}
                                        disabled={isSubmittingReply}
                                        className={cn(
                                            'min-h-[3rem] resize-y bg-background text-[13px] shadow-none sm:text-sm',
                                            replyError &&
                                                'border-destructive focus-visible:border-destructive',
                                        )}
                                    />
                                    {replyError ? (
                                        <p
                                            className="text-xs text-destructive"
                                            role="alert"
                                        >
                                            {replyError}
                                        </p>
                                    ) : null}
                                    <div className="flex flex-wrap items-center justify-between gap-1.5">
                                        <p className="text-[11px] text-muted-foreground tabular-nums">
                                            {replyBody.length}/{MAX_LENGTH}
                                        </p>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={isSubmittingReply}
                                                onClick={cancelReply}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={
                                                    isSubmittingReply ||
                                                    replyBody.trim() === ''
                                                }
                                            >
                                                {isSubmittingReply
                                                    ? 'Posting…'
                                                    : 'Post reply'}
                                            </Button>
                                        </div>
                                    </div>
                                </form>
                            ) : null}
                        </>
                    )}
                </div>
            </article>
        );
    };

    return (
        <section
            id="resource-comments"
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
                                        placeholder="Share your thoughts…"
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
                                                        'font-medium text-warning',
                                                    remaining <= 0 &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {body.length}/{MAX_LENGTH}
                                            </span>
                                        </p>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={
                                                isSubmitting ||
                                                body.trim() === ''
                                            }
                                        >
                                            {isSubmitting ? 'Posting…' : 'Post'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    )}
                </div>

                {commentItems.length === 0 ? (
                    <SiteEmptyState
                        icon={MessageSquare}
                        title="No comments yet"
                        className="min-h-0 rounded-none border-0 bg-transparent py-6"
                    />
                ) : (
                    <ul className="divide-y divide-border/60">
                        {commentItems.map((comment) => (
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

                <div className="border-t border-border/60 px-3 py-3 sm:px-4">
                    <SitePagination
                        pagination={comments}
                        pageUrl={(page) =>
                            resourceCommentsRoute(resourceId, {
                                query: { page },
                            }).url
                        }
                        ariaLabel="Comments pagination"
                        itemLabel="comments"
                        only={['comments', 'commentsCount', 'activeTab']}
                        onSuccess={() => {
                            document
                                .getElementById('resource-comments')
                                ?.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start',
                                });
                        }}
                    />
                </div>
            </div>
        </section>
    );
}
