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
            'category' => $doc->category,
            'publishedAt' => self::dateString($doc->published_at),
            'updatedAt' => self::dateString($doc->updated_at),
            'readingMinutes' => $doc->readingMinutes(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Doc $doc): array
    {
        return [
            ...self::card($doc),
            'body' => str($doc->body ?? '')->sanitizeHtml()->toString(),
            'headings' => $doc->headings(),
        ];
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
