<?php

namespace App\Notifications;

use App\Models\GameComment;
use App\Support\ResourceShowUrl;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Database-only; sent synchronously so it works without a queue worker.
 * Prefer queueing only when adding slow channels (mail, etc.).
 */
class CommentRepliedNotification extends Notification
{
    public function __construct(public GameComment $comment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'comment.replied';
    }

    /**
     * @return array{
     *     title: string,
     *     body: string,
     *     url: string,
     *     actor: array{id: int, name: string, avatar: string|null},
     *     comment_id: int,
     *     game_id: int,
     *     game_slug: string,
     *     game_title: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        $this->comment->loadMissing(['user', 'game']);

        $actor = $this->comment->user;
        $game = $this->comment->game;
        $actorName = $actor->name;

        return [
            'title' => __(':name replied to your comment', [
                'name' => $actorName,
            ]),
            'body' => Str::limit($this->comment->body, 140),
            'url' => ResourceShowUrl::comment($game->slug, $this->comment->id),
            'actor' => [
                'id' => $actor->id,
                'name' => $actorName,
                'avatar' => $actor->avatar,
            ],
            'comment_id' => $this->comment->id,
            'game_id' => $game->id,
            'game_slug' => $game->slug,
            'game_title' => $game->title,
        ];
    }
}
