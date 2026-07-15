<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameRelease;
use Illuminate\Support\Carbon;

class GamePresenter
{
    /** @return array<string, mixed> */
    public static function card(Game $game, bool $includeTags = true): array
    {
        $categoryName = $game->category?->name;

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'thumbnail' => self::mediaUrl($game->cover_path ?: $game->cover_url),
            'category' => filled($categoryName) ? $categoryName : 'Uncategorized',
            'developer' => $game->developer ?? 'Unknown',
            'platforms' => $game->releases
                ->flatMap->platforms
                ->unique('slug')
                ->map(fn ($platform): array => [
                    'name' => $platform->name,
                    'slug' => $platform->slug,
                ])
                ->values()
                ->all(),
            'languages' => $game->releases->flatMap->languages->pluck('name')->unique()->values()->all(),
            'version' => $game->releases
                ->pluck('version')
                ->map(fn (?string $version): ?string => filled($version) ? trim($version) : null)
                ->filter()
                ->first(),
            'tags' => $includeTags
                ? $game->tags->pluck('name')->values()->all()
                : [],
            'publishedAt' => self::dateString($game->published_at),
            'views' => $game->views_count,
        ];
    }

    /** @return array{id: string, title: string, subtitle: string|null, thumbnail: string} */
    public static function search(Game $game): array
    {
        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'thumbnail' => self::mediaUrl($game->cover_path ?: $game->cover_url),
        ];
    }

    /** @return array<string, mixed> */
    public static function recentUpdate(Game $game): array
    {
        $updatedAt = $game->downloads_updated_at ?? $game->published_at;

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'thumbnail' => self::mediaUrl($game->cover_path ?: $game->cover_url),
            'developer' => $game->developer ?? 'Unknown',
            'version' => $game->releases
                ->pluck('version')
                ->map(fn (?string $version): ?string => filled($version) ? trim($version) : null)
                ->filter()
                ->first(),
            'platforms' => $game->releases
                ->flatMap->platforms
                ->unique('slug')
                ->map(fn ($platform): array => [
                    'name' => $platform->name,
                    'slug' => $platform->slug,
                ])
                ->values()
                ->all(),
            'languages' => $game->releases
                ->flatMap->languages
                ->pluck('name')
                ->unique()
                ->values()
                ->all(),
            'updatedAt' => self::dateTimeString($updatedAt),
            'activityType' => $game->downloads_updated_at !== null ? 'updated' : 'published',
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(
        Game $game,
        bool $includeScreenshots = true,
        bool $includeReleases = true,
        bool $includeDescription = true,
        bool $includeTags = true,
    ): array {
        return [
            ...self::card($game, includeTags: $includeTags),
            'subtitle' => $game->subtitle,
            'description' => $includeDescription
                ? str($game->description ?? '')->sanitizeHtml()->toString()
                : '',
            'developer' => $game->developer ?? 'Unknown',
            'releaseDate' => self::dateString($game->release_date),
            'downloads' => $game->downloads_count,
            'screenshots' => $includeScreenshots
                ? $game->screenshots
                    ->map(fn ($screenshot): string => self::mediaUrl($screenshot->path ?: $screenshot->url))
                    ->values()
                    ->all()
                : [],
            'releases' => $includeReleases
                ? $game->releases
                    ->map(fn (GameRelease $release): array => [
                        'id' => $release->id,
                        'title' => $release->title ?: null,
                        'platforms' => $release->platforms
                            ->map(fn ($platform): array => [
                                'name' => $platform->name,
                                'slug' => $platform->slug,
                            ])
                            ->values()
                            ->all(),
                        'languages' => $release->languages->pluck('name')->values()->all(),
                        'version' => $release->version,
                        'fileSize' => $release->file_size,
                        'description' => $release->description,
                        'publishedAt' => self::dateString($release->published_at),
                        'downloadLinks' => $release->relationLoaded('downloadLinks')
                            ? $release->downloadLinks
                                ->map(fn ($link): array => [
                                    'id' => $link->id,
                                    'label' => $link->label ?: 'Download',
                                    'url' => $link->url,
                                ])
                                ->values()
                                ->all()
                            : [],
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    private static function mediaUrl(?string $path): string
    {
        return Media::url($path);
    }

    private static function dateString(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse((string) $date)->toDateString();
    }

    private static function dateTimeString(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return $date instanceof Carbon
            ? $date->toIso8601String()
            : Carbon::parse((string) $date)->toIso8601String();
    }
}
