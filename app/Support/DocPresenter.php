<?php

namespace App\Support;

use App\Models\Doc;
use Illuminate\Support\Carbon;

class DocPresenter
{
    /** @return array<string, mixed> */
    public static function card(Doc $doc): array
    {
        return [
            'slug' => $doc->slug,
            'title' => $doc->title,
            'excerpt' => $doc->excerpt ?? '',
            'thumbnail' => self::thumbnailUrl($doc),
            'publishedAt' => self::dateString($doc->published_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Doc $doc): array
    {
        return [
            ...self::card($doc),
            'body' => str($doc->body ?? '')->sanitizeHtml()->toString(),
        ];
    }

    private static function thumbnailUrl(Doc $doc): ?string
    {
        if (blank($doc->cover_path)) {
            return null;
        }

        return Media::url($doc->cover_path);
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
