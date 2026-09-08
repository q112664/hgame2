<?php

namespace App\Support;

use App\GameStatus;
use App\Models\Game;
use App\Models\GameDetailTranslation;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\GameScreenshot;

final class GameApiPayload
{
    /**
     * @return list<string>
     */
    public static function detailRelations(): array
    {
        return [
            'category',
            'tags',
            'screenshots',
            'detailTranslations.language',
            'releases.contributor',
            'releases.platforms',
            'releases.languages',
            'releases.downloadLinks',
        ];
    }

    public static function reload(Game $game): Game
    {
        return $game->fresh(self::detailRelations()) ?? $game->load(self::detailRelations());
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Game $game): array
    {
        $status = $game->getAttribute('status');

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'status' => $status instanceof GameStatus
                ? $status->value
                : (string) $status,
            'category' => $game->category?->name,
            'developer' => $game->developer,
            'url' => route('resources.show', $game),
            'cover_url' => Media::url($game->cover_path ?: $game->cover_url),
            'published_at' => $game->published_at?->toIso8601String(),
            'screenshots_count' => $game->screenshots_count
                ?? $game->screenshots->count(),
            'releases_count' => $game->releases_count
                ?? $game->releases->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Game $game): array
    {
        $game->loadMissing(self::detailRelations());

        $status = $game->getAttribute('status');

        return [
            'id' => $game->slug,
            'title' => $game->title,
            'subtitle' => $game->subtitle,
            'status' => $status instanceof GameStatus
                ? $status->value
                : (string) $status,
            'category' => $game->category?->name,
            'tags' => $game->tags->pluck('name')->values()->all(),
            'developer' => $game->developer,
            'source_name' => $game->source_name,
            'source_id' => $game->source_id,
            'source_url' => $game->source_url,
            'source' => GameSource::present(
                $game->source_name,
                $game->source_id,
                $game->source_url,
            ),
            'release_date' => $game->release_date?->toDateString(),
            'description' => $game->description,
            'detail_versions' => $game->detailTranslations
                ->map(fn (GameDetailTranslation $translation): array => [
                    'id' => $translation->id,
                    'language' => [
                        'name' => $translation->language?->name,
                        'code' => $translation->language?->code,
                    ],
                    'description' => $translation->description,
                    'sort_order' => (int) $translation->sort_order,
                ])
                ->values()
                ->all(),
            'cover_url' => Media::url($game->cover_path ?: $game->cover_url),
            'published_at' => $game->published_at?->toIso8601String(),
            'url' => route('resources.show', $game),
            'screenshots' => $game->screenshots
                ->map(fn (GameScreenshot $screenshot): string => self::screenshotUrl($screenshot))
                ->values()
                ->all(),
            'screenshot_items' => $game->screenshots
                ->map(fn (GameScreenshot $screenshot): array => [
                    'id' => $screenshot->id,
                    'url' => self::screenshotUrl($screenshot),
                    'sort_order' => (int) $screenshot->sort_order,
                ])
                ->values()
                ->all(),
            'releases' => $game->releases
                ->map(fn (GameRelease $release): array => [
                    'id' => $release->id,
                    'title' => $release->title,
                    'platforms' => $release->platforms->pluck('name')->values()->all(),
                    'languages' => $release->languages->pluck('name')->values()->all(),
                    'version' => $release->version,
                    'file_size' => $release->file_size,
                    'description' => $release->description,
                    'is_active' => (bool) $release->is_active,
                    'published_at' => $release->published_at?->toIso8601String(),
                    'contributor' => $release->relationLoaded('contributor') && $release->contributor !== null
                        ? [
                            'name' => $release->contributor->name,
                            'email' => $release->contributor->email,
                        ]
                        : null,
                    'download_links' => $release->downloadLinks
                        ->pluck('url')
                        ->values()
                        ->all(),
                    'download_link_items' => $release->downloadLinks
                        ->map(fn (GameDownloadLink $link): array => [
                            'id' => $link->id,
                            'url' => $link->url,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'screenshots_count' => $game->screenshots->count(),
            'releases_count' => $game->releases->count(),
        ];
    }

    private static function screenshotUrl(GameScreenshot $screenshot): string
    {
        return Media::url($screenshot->path ?: $screenshot->url);
    }
}
