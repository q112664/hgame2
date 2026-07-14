<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameRelease;

class GamePresenter
{
    /** @return array<string, mixed> */
    public static function card(Game $game): array
    {
        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'thumbnail' => self::mediaUrl($game->cover_path ?: $game->cover_url),
            'category' => $game->category?->name ?? 'Uncategorized',
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
            'tags' => $game->tags->pluck('name')->values()->all(),
            'publishedAt' => $game->published_at?->toDateString(),
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
    public static function detail(Game $game): array
    {
        return [
            ...self::card($game),
            'subtitle' => $game->subtitle,
            'description' => str($game->description ?? '')->sanitizeHtml()->toString(),
            'developer' => $game->developer ?? 'Unknown',
            'releaseDate' => $game->release_date?->toDateString(),
            'downloads' => $game->downloads_count,
            'screenshots' => $game->screenshots
                ->map(fn ($screenshot): string => self::mediaUrl($screenshot->path ?: $screenshot->url))
                ->values()
                ->all(),
            'releases' => $game->releases
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
                    'publishedAt' => $release->published_at?->toDateString(),
                    'downloadLinks' => $release->downloadLinks
                        ->map(fn ($link): array => [
                            'id' => $link->id,
                            'label' => $link->label ?: 'Download',
                            'url' => $link->url,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private static function mediaUrl(?string $path): string
    {
        return Media::url($path);
    }
}
