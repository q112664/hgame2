<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Admin broadcast to all users. Database-only, synchronous.
 */
class SystemBroadcastNotification extends Notification
{
    public function __construct(
        public string $title,
        public ?string $body = null,
        public ?string $url = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'system.broadcast';
    }

    /**
     * @return array{
     *     title: string,
     *     body: string|null,
     *     url: string|null
     * }
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
