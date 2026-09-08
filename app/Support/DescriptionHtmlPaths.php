<?php

namespace App\Support;

final class DescriptionHtmlPaths
{
    /**
     * @return list<string>
     */
    public static function extract(string $description): array
    {
        if ($description === '') {
            return [];
        }

        preg_match_all(
            '#(?:/storage/|https?://[^"\'>\s]+/)(games/(?:covers|screenshots|content)/[^"\'>\s?]+)#i',
            $description,
            $matches,
        );

        $paths = [];

        foreach ($matches[1] as $path) {
            $paths[] = ltrim($path, '/');
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    public static function orphaned(string $oldHtml, string $newHtml): array
    {
        return array_values(array_diff(self::extract($oldHtml), self::extract($newHtml)));
    }
}
