<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Normalizes Laravel database notifications into a stable frontend payload.
 */
class AppNotification
{
    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     body: string|null,
     *     url: string|null,
     *     actor: array{id: int, name: string, avatar: string|null}|null,
     *     readAt: string|null,
     *     createdAt: string|null,
     *     data: array<string, mixed>
     * }
     */
    public static function present(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($notification->data) ? $notification->data : [];

        $actor = null;

        if (isset($data['actor']) && is_array($data['actor'])) {
            $actor = [
                'id' => (int) ($data['actor']['id'] ?? 0),
                'name' => (string) ($data['actor']['name'] ?? ''),
                'avatar' => isset($data['actor']['avatar'])
                    ? (is_string($data['actor']['avatar']) ? $data['actor']['avatar'] : null)
                    : null,
            ];

            if ($actor['id'] === 0 || $actor['name'] === '') {
                $actor = null;
            }
        }

        $type = is_string($notification->type) && $notification->type !== ''
            ? $notification->type
            : (string) ($data['type'] ?? 'general');

        // Prefer short databaseType values over FQCN when stored.
        if (str_contains($type, '\\')) {
            $type = (string) ($data['type'] ?? class_basename($type));
        }

        return [
            'id' => (string) $notification->id,
            'type' => $type,
            'title' => is_string($data['title'] ?? null)
                ? $data['title']
                : __('Notification'),
            'body' => is_string($data['body'] ?? null) ? $data['body'] : null,
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'actor' => $actor,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
            'data' => $data,
        ];
    }
}
