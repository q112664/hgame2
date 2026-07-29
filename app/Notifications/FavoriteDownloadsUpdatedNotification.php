<?php

namespace App\Notifications;

use App\Models\Game;
use Illuminate\Notifications\Notification;

class FavoriteDownloadsUpdatedNotification extends Notification
{
    public function __construct(public Game $game) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'favorite.downloads_updated';
    }

    /**
     * @return array{
     *     title: string,
     *     body: string,
     *     url: string,
     *     game_id: int,
     *     game_slug: string,
     *     game_title: string
     * }
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Downloads updated: :title', [
                'title' => $this->game->title,
            ]),
            'body' => __('A resource you favorited has new or updated downloads.'),
            'url' => route('resources.downloads', $this->game->slug, absolute: false),
            'game_id' => $this->game->id,
            'game_slug' => $this->game->slug,
            'game_title' => $this->game->title,
        ];
    }
}
