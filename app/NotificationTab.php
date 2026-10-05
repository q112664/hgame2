<?php

namespace App;

enum NotificationTab: string
{
    case All = 'all';
    case Favorites = 'favorites';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::All => __('All'),
            self::Favorites => __('Favorite updates'),
            self::System => __('Announcements'),
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
            self::Favorites => ['favorite.downloads_updated'],
            self::System => ['system.broadcast'],
        };
    }

    public static function tryFromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }
}
