<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameRelease;
use Illuminate\Support\Str;

class GamePresenter
{
    /** @return array<string, mixed> */
    public static function card(Game $game): array
    {
        return [
            'id' => $game->slug,
            'title' => $game->title,
            'thumbnail' => self::mediaUrl($game->cover_path ?: $game->cover_url),
            'category' => $game->category?->name ?? 'Uncategorized',
            'platforms' => $game->releases->flatMap->platforms->pluck('name')->unique()->values()->all(),
            'languages' => $game->releases->flatMap->languages->pluck('name')->unique()->values()->all(),
            'tags' => $game->tags->pluck('name')->values()->all(),
            'publishedAt' => $game->published_at?->toDateString(),
            'views' => $game->views_count,
        ];
    }

    /** @return array{id: string, title: string, thumbnail: string} */
    public static function search(Game $game): array
    {
        return [
            'id' => $game->slug,
            'title' => $game->title,
            'thumbnail' => self::mediaUrl($game->cover_path ?: $game->cover_url),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Game $game): array
    {
        return [
            ...self::card($game),
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
                    'platforms' => $release->platforms->pluck('name')->values()->all(),
                    'languages' => $release->languages->pluck('name')->values()->all(),
                    'version' => $release->version,
                    'fileSize' => $release->file_size,
                    'description' => str($release->description ?? '')->sanitizeHtml()->toString(),
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
        if (blank($path)) {
            return '';
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset('storage/'.$path);
    }
}
