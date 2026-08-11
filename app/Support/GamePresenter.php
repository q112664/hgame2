<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameRelease;
use App\Models\User;
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
            'thumbnail' => self::cardThumbnailUrl($game),
            'thumbnailFallback' => self::mediaUrl($game->cover_path ?: $game->cover_url),
            'category' => filled($categoryName) ? $categoryName : 'Uncategorized',
            'categorySlug' => $game->category?->slug,
            'developer' => $game->developer ?? 'Unknown',
            'source' => GameSource::present(
                $game->source_name,
                $game->source_id,
                $game->source_url,
            ),
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
                ->unique('code')
                ->map(fn ($language): array => [
                    'name' => $language->name,
                    'code' => $language->code,
                ])
                ->values()
                ->all(),
            'version' => $game->releases
                ->pluck('version')
                ->map(fn (?string $version): ?string => filled($version) ? trim($version) : null)
                ->filter()
                ->first(),
            'tags' => $includeTags
                ? $game->tags
                    ->map(fn ($tag): array => [
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ])
                    ->values()
                    ->all()
                : [],
            'releaseDate' => self::dateString($game->release_date),
            'publishedAt' => self::dateString($game->published_at),
            'downloadsUpdatedAt' => self::dateString($game->downloads_updated_at),
            'views' => $game->views_count,
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
        $description = $includeDescription
            ? str($game->description ?? '')->sanitizeHtml()->toString()
            : '';

        return [
            ...self::card($game, includeTags: $includeTags),
            // Hero uses the card thumbnail; cover is the full-size original for the lightbox.
            'cover' => self::mediaUrl($game->cover_path ?: $game->cover_url),
            'subtitle' => $game->subtitle,
            'description' => $description,
            'detailVersions' => $includeDescription
                ? self::detailVersions($game, $description)
                : [],
            'developer' => $game->developer ?? 'Unknown',
            'source' => GameSource::present(
                $game->source_name,
                $game->source_id,
                $game->source_url,
            ),
            'releaseDate' => self::dateString($game->release_date),
            'downloads' => $game->downloads_count,
            /** Latest package contributor for the hero (0–1 entries). */
            'contributors' => self::resourceContributors($game),
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
                        'languages' => $release->languages
                            ->map(fn ($language): array => [
                                'name' => $language->name,
                                'code' => $language->code,
                            ])
                            ->values()
                            ->all(),
                        'version' => $release->version,
                        'fileSize' => $release->file_size,
                        'description' => str($release->description ?? '')->sanitizeHtml()->toString(),
                        'publishedAt' => self::dateString($release->published_at),
                        'contributor' => $release->relationLoaded('contributor') && $release->contributor !== null
                            ? [
                                'id' => $release->contributor->id,
                                'name' => $release->contributor->name,
                                'avatar' => $release->contributor->avatar,
                            ]
                            : null,
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

    /**
     * Latest package contributor for the resource hero (at most one).
     *
     * Prefers the release with the newest published_at, then created_at, then id.
     *
     * @return list<array{id: int, name: string, avatar: string|null}>
     */
    private static function resourceContributors(Game $game): array
    {
        if (! $game->relationLoaded('releases')) {
            return [];
        }

        $latest = $game->releases
            ->filter(function (GameRelease $release): bool {
                if (! $release->relationLoaded('contributor')) {
                    return false;
                }

                return $release->getRelation('contributor') instanceof User;
            })
            ->sort(function (GameRelease $left, GameRelease $right): int {
                $leftTime = $left->published_at?->getTimestamp()
                    ?? $left->created_at?->getTimestamp()
                    ?? 0;
                $rightTime = $right->published_at?->getTimestamp()
                    ?? $right->created_at?->getTimestamp()
                    ?? 0;

                if ($leftTime !== $rightTime) {
                    return $rightTime <=> $leftTime;
                }

                return $right->id <=> $left->id;
            })
            ->first();

        if ($latest === null) {
            return [];
        }

        /** @var User $contributor */
        $contributor = $latest->getRelation('contributor');

        return [[
            'id' => $contributor->id,
            'name' => $contributor->name,
            'avatar' => $contributor->avatar,
        ]];
    }

    /**
     * @return list<array{code: string, name: string, html: string, isDefault: bool}>
     */
    private static function detailVersions(Game $game, string $defaultDescription): array
    {
        $versions = [];

        if (filled($defaultDescription)) {
            $versions[] = [
                'code' => 'original',
                'name' => 'English',
                'html' => $defaultDescription,
                'isDefault' => true,
            ];
        }

        if ($game->relationLoaded('detailTranslations')) {
            foreach ($game->detailTranslations as $translation) {
                $language = $translation->language;

                if ($language === null) {
                    continue;
                }

                $versions[] = [
                    'code' => $language->code,
                    'name' => $language->name,
                    'html' => str($translation->description ?? '')->sanitizeHtml()->toString(),
                    'isDefault' => false,
                ];
            }
        }

        if ($versions !== [] && ! collect($versions)->contains('isDefault', true)) {
            $versions[0]['isDefault'] = true;
        }

        return $versions;
    }

    private static function cardThumbnailUrl(Game $game): string
    {
        if (filled($game->cover_path)) {
            return MediaThumbnail::url($game->cover_path);
        }

        return self::mediaUrl($game->cover_url);
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
}
