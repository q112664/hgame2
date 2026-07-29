<?php

namespace App;

enum NotificationTab: string
{
    case All = 'all';
    case Comments = 'comments';
    case Favorites = 'favorites';

    public function label(): string
    {
        return match ($this) {
            self::All => __('All'),
            self::Comments => __('Comments'),
            self::Favorites => __('Favorites'),
        };
    }

    /**
     * Database notification type values for this tab.
     * Null means no type filter (all notifications).
     *
     * @return list<string>|null
     */
    public function types(): ?array
    {
        return match ($this) {
            self::All => null,
            self::Comments => ['comment.replied'],
            self::Favorites => ['favorite.downloads_updated'],
        };
    }

    public static function tryFromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }
}
