import { router, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
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
    rating?: number | null;
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
    ratingsAvg?: number;
    ratingsCount?: number;
};

const RATING_MAX = 5;
const COMMENT_PARTIALS = [
    'comments',
    'commentsCount',
    'ratingsAvg',
    'ratingsCount',
] as const;

function RatingStars({
    value,
    onChange,
    size = 'md',
    readOnly = false,
    label = 'Rating',
}: {
    value: number | null;
    onChange?: (value: number | null) => void;
    size?: 'sm' | 'md';
    readOnly?: boolean;
    label?: string;
}) {
    const [hover, setHover] = useState<number | null>(null);
    const display = hover ?? value ?? 0;
    const iconClass = size === 'sm' ? 'size-3.5' : 'size-5';

    if (readOnly) {
        if (value === null || value < 1) {
            return null;
        }

        return (
            <span
                className="inline-flex items-center gap-0.5"
                aria-label={`${value} out of ${RATING_MAX} stars`}
            >
                {Array.from({ length: RATING_MAX }, (_, index) => {
                    const score = index + 1;

                    return (
                        <Star
                            key={score}
                            className={cn(
                                iconClass,
                                score <= value
                                    ? 'fill-warning text-warning'
                                    : 'text-muted-foreground/30',
                            )}
                            aria-hidden
                        />
                    );
                })}
            </span>
        );
    }

    return (
        <div
            className="inline-flex items-center gap-0.5"
            role="radiogroup"
            aria-label={label}
            onMouseLeave={() => setHover(null)}
        >
            {Array.from({ length: RATING_MAX }, (_, index) => {
                const score = index + 1;
                const active = score <= display;

                return (
                    <button
                        key={score}
                        type="button"
                        role="radio"
                        aria-checked={value === score}
                        aria-label={`${score} star${score === 1 ? '' : 's'}`}
                        className={cn(
                            'rounded-sm p-0.5 transition-colors',
                            'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                            active
                                ? 'text-warning'
                                : 'text-muted-foreground/35 hover:text-warning/80',
                        )}
                        onMouseEnter={() => setHover(score)}
                        onClick={() =>
                            onChange?.(value === score ? null : score)
                        }
                    >
                        <Star
                            className={cn(
                                iconClass,
                                active && 'fill-warning',
                            )}
                            aria-hidden
                        />
                    </button>
                );
            })}
        </div>
    );
}

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
    replyToName = null,
    showReplyToName = false,
}: {
    body: string;
    /** Used to remove a legacy prefix from the stored reply body. */
    replyToName?: string | null;
    /** Whether to show the reply target in the rendered text. */
    showReplyToName?: boolean;
}) {
    const text = normalizeCommentBody(body, replyToName);

    return (
        <p className="mt-0.5 text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
            {showReplyToName && replyToName ? (
                <>
                    <span className="text-muted-foreground">
                        @{replyToName}
                    </span>{' '}
                </>
            ) : null}
            {text}
        </p>
    );
}

function CommentMetaDot() {
    return (
        <span className="text-muted-foreground/40" aria-hidden>
            ·
        </span>
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
                'rounded-sm py-0.5 text-xs text-muted-foreground transition-colors',
                'hover:text-foreground',
                'disabled:pointer-events-none disabled:opacity-50',
                'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                destructive && 'hover:text-destructive',
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
    ratingsAvg = 0,
    ratingsCount = 0,
}: Props) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();
    const isAuthenticated = Boolean(page.props.auth.user);
    const [body, setBody] = useState('');
    const [rating, setRating] = useState<number | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editBody, setEditBody] = useState('');
    const [editRating, setEditRating] = useState<number | null>(null);
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
        score,
        onStart,
        onDone,
        onFail,
        onPosted,
    }: {
        content: string;
        parentId: number | null;
        score?: number | null;
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
                rating: parentId === null ? (score ?? null) : null,
            },
            {
                preserveScroll: true,
                only: [...COMMENT_PARTIALS],
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
                            : typeof errors.rating === 'string'
                              ? errors.rating
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
            score: rating,
            onStart: () => setIsSubmitting(true),
            onDone: () => setIsSubmitting(false),
            onFail: (message) => setError(message),
            onPosted: () => {
                setBody('');
                setRating(null);
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
        setEditRating(comment.rating ?? null);
        setEditError(null);
        cancelReply();
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditBody('');
        setEditRating(null);
        setEditError(null);
    };

    const saveEdit = (commentId: number, isRoot: boolean) => {
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
            {
                body: trimmed,
                rating: isRoot ? editRating : null,
            },
            {
                preserveScroll: true,
                only: [...COMMENT_PARTIALS],
                onSuccess: () => cancelEdit(),
                onError: (errors) => {
                    setEditError(
                        typeof errors.body === 'string'
                            ? errors.body
                            : typeof errors.rating === 'string'
                              ? errors.rating
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
                only: [...COMMENT_PARTIALS],
                onFinish: () => setDeletingId(null),
            },
        );
    };

    const remaining = MAX_LENGTH - body.length;
    const nearLimit = remaining <= 100;

    const renderComment = (
        comment: ResourceCommentReply,
        options: { nested?: boolean } = {},
    ) => {
        const isEditing = editingId === comment.id;
        const nested = options.nested ?? false;

        const isHighlighted = highlightedId === comment.id;
        const replyTo = nested ? comment.replyTo : null;
        const replyToName = replyTo?.name ?? null;

        // Nested replies always show the target so threads stay readable.
        const showReplyTo = replyToName !== null;

        const isReplyingHere = replyTarget?.commentId === comment.id;
        const showActions = !isEditing && !isReplyingHere;

        return (
            <article
                key={comment.id}
                id={commentDomId(comment.id)}
                data-comment-id={comment.id}
                className={cn(
                    'flex scroll-mt-24 gap-2.5',
                    nested && 'min-w-0',
                    isHighlighted && 'rounded-md bg-muted/40',
                )}
            >
                <UserAvatar
                    user={comment.user}
                    className={cn(
                        'mt-0.5 shrink-0',
                        nested ? 'size-7' : 'size-8',
                    )}
                    fallbackClassName="rounded-full bg-muted text-[10px] text-muted-foreground"
                />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                        <span className="text-sm font-medium text-foreground">
                            {comment.user.name}
                        </span>
                        {comment.user.isAdmin ? (
                            <>
                                <CommentMetaDot />
                                <span className="text-xs text-muted-foreground">
                                    Admin
                                </span>
                            </>
                        ) : null}
                        {comment.createdAt ? (
                            <>
                                <CommentMetaDot />
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
                            <>
                                <CommentMetaDot />
                                <span className="text-xs text-muted-foreground">
                                    edited
                                </span>
                            </>
                        ) : null}
                    </div>

                    {isEditing ? (
                        <div className="mt-1.5 flex flex-col gap-2">
                            {!nested ? (
                                <RatingStars
                                    value={editRating}
                                    onChange={setEditRating}
                                    label="Edit rating"
                                />
                            ) : null}
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
                                        saveEdit(comment.id, !nested);
                                    }
                                }}
                                rows={2}
                                maxLength={MAX_LENGTH}
                                disabled={isSavingEdit}
                                className="min-h-14 resize-y text-sm shadow-none"
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
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={
                                        isSavingEdit || editBody.trim() === ''
                                    }
                                    onClick={() =>
                                        saveEdit(comment.id, !nested)
                                    }
                                >
                                    {isSavingEdit ? 'Saving…' : 'Save'}
                                </Button>
                                <CommentActionButton
                                    disabled={isSavingEdit}
                                    onClick={cancelEdit}
                                >
                                    Cancel
                                </CommentActionButton>
                            </div>
                        </div>
                    ) : (
                        <>
                            {!nested && comment.rating ? (
                                <div className="mt-1">
                                    <RatingStars
                                        value={comment.rating}
                                        readOnly
                                        size="sm"
                                    />
                                </div>
                            ) : null}
                            <CommentBody
                                body={comment.body}
                                replyToName={replyToName}
                                showReplyToName={showReplyTo}
                            />
                            {showActions ? (
                                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <CommentActionButton
                                        onClick={() => beginReply(comment)}
                                    >
                                        Reply
                                    </CommentActionButton>
                                    {comment.canEdit ? (
                                        <CommentActionButton
                                            onClick={() => startEdit(comment)}
                                        >
                                            Edit
                                        </CommentActionButton>
                                    ) : null}
                                    {comment.canDelete ? (
                                        <CommentActionButton
                                            destructive
                                            disabled={
                                                deletingId === comment.id
                                            }
                                            onClick={() =>
                                                remove(comment.id)
                                            }
                                        >
                                            {deletingId === comment.id
                                                ? 'Deleting…'
                                                : 'Delete'}
                                        </CommentActionButton>
                                    ) : null}
                                </div>
                            ) : null}
                            {isReplyingHere ? (
                                <form
                                    className="mt-2 flex flex-col gap-2"
                                    onSubmit={submitInlineReply}
                                >
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
                                        placeholder={`Reply to ${replyTarget.userName}…`}
                                        rows={2}
                                        maxLength={MAX_LENGTH}
                                        disabled={isSubmittingReply}
                                        className={cn(
                                            'min-h-14 resize-y text-sm shadow-none',
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
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
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
                                                : 'Reply'}
                                        </Button>
                                        <CommentActionButton
                                            disabled={isSubmittingReply}
                                            onClick={cancelReply}
                                        >
                                            Cancel
                                        </CommentActionButton>
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
            aria-label="Reviews"
            className="rounded-md border border-border bg-card"
        >
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 px-4 py-3 sm:px-5">
                <div className="flex items-baseline gap-2">
                    <h2 className="font-heading text-sm font-semibold tracking-tight text-foreground">
                        Reviews
                    </h2>
                    {totalCount > 0 ? (
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {totalCount}
                        </span>
                    ) : null}
                </div>
                {ratingsCount > 0 ? (
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <RatingStars
                            value={Math.round(ratingsAvg)}
                            readOnly
                            size="sm"
                        />
                        <span className="tabular-nums font-medium text-foreground">
                            {ratingsAvg.toFixed(1)}
                        </span>
                        <span className="tabular-nums">
                            ({ratingsCount}{' '}
                            {ratingsCount === 1 ? 'rating' : 'ratings'})
                        </span>
                    </div>
                ) : null}
            </header>

            <div className="flex flex-col">
                <div className="border-b border-border/70 px-4 py-3.5 sm:px-5">
                    {!isAuthenticated ? (
                        <p
                            role="region"
                            aria-label="Sign in to comment"
                            className="text-sm text-muted-foreground"
                        >
                            <button
                                type="button"
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                                onClick={() =>
                                    openAuthDialog('login', {
                                        redirect: page.url,
                                    })
                                }
                            >
                                Log in
                            </button>
                            <span className="mx-1.5 text-muted-foreground/50">
                                or
                            </span>
                            <button
                                type="button"
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                                onClick={() =>
                                    openAuthDialog('register', {
                                        redirect: page.url,
                                    })
                                }
                            >
                                sign up
                            </button>
                            <span> to leave a review.</span>
                        </p>
                    ) : (
                        <form onSubmit={submit} className="flex flex-col gap-2.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Your rating
                                </span>
                                <RatingStars
                                    value={rating}
                                    onChange={setRating}
                                    label="Your rating"
                                />
                                {rating !== null ? (
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {rating}/{RATING_MAX}
                                    </span>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        Optional
                                    </span>
                                )}
                            </div>
                            <label
                                className="sr-only"
                                htmlFor="resource-comment-body"
                            >
                                Write a review
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
                                        (event.metaKey || event.ctrlKey) &&
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
                                    'min-h-14 resize-y text-sm shadow-none',
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
                            <div className="flex flex-wrap items-center justify-end gap-3">
                                {body.length > 0 ? (
                                    <p
                                        className={cn(
                                            'mr-auto text-xs text-muted-foreground tabular-nums',
                                            nearLimit && 'text-warning',
                                            remaining <= 0 &&
                                                'text-destructive',
                                        )}
                                    >
                                        {body.length}/{MAX_LENGTH}
                                    </p>
                                ) : null}
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={
                                        isSubmitting || body.trim() === ''
                                    }
                                >
                                    {isSubmitting ? 'Posting…' : 'Post review'}
                                </Button>
                            </div>
                        </form>
                    )}
                </div>

                {commentItems.length === 0 ? (
                    <SiteEmptyState
                        title="No reviews yet"
                        className="min-h-0 rounded-none border-0 bg-transparent py-10"
                    />
                ) : (
                    <ul className="divide-y divide-border/60">
                        {commentItems.map((comment) => (
                            <li
                                key={comment.id}
                                className="flex flex-col gap-3 px-4 py-4 sm:px-5"
                            >
                                {renderComment(comment)}
                                {comment.replies &&
                                comment.replies.length > 0 ? (
                                    <ul
                                        className="flex flex-col gap-3 border-l border-border/60 pl-3 sm:gap-3.5 sm:pl-4"
                                        aria-label="Replies"
                                    >
                                        {comment.replies.map((reply) => (
                                            <li key={reply.id}>
                                                {renderComment(reply, {
                                                    nested: true,
                                                })}
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}

                {comments.last_page > 1 ? (
                    <div className="border-t border-border/70 px-4 py-3 sm:px-5">
                        <SitePagination
                            pagination={comments}
                            pageUrl={(page) =>
                                resourceCommentsRoute(resourceId, {
                                    query: { page },
                                }).url
                            }
                            ariaLabel="Reviews pagination"
                            itemLabel="reviews"
                            only={[
                                'comments',
                                'commentsCount',
                                'ratingsAvg',
                                'ratingsCount',
                                'activeTab',
                            ]}
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
                ) : null}
            </div>
        </section>
    );
}
