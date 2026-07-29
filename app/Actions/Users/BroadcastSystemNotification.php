<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\SystemBroadcastNotification;
use Illuminate\Support\Facades\Notification;

class BroadcastSystemNotification
{
    /**
     * Send a system notification to every registered user.
     *
     * @return int Number of users notified
     */
    public function __invoke(string $title, ?string $body = null, ?string $url = null): int
    {
        $title = trim($title);
        $body = filled($body) ? trim((string) $body) : null;
        $url = filled($url) ? trim((string) $url) : null;

        $notification = new SystemBroadcastNotification($title, $body, $url);
        $count = 0;

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($notification, &$count): void {
                Notification::send($users, $notification);
                $count += $users->count();
            });

        return $count;
    }
}
