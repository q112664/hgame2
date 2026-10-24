<?php

namespace App\Support;

/**
 * Description HTML that is safe to render, with its artwork deferred.
 *
 * Descriptions can embed whole screenshot sets; those images used to load eagerly
 * with the page. The sanitizer runs first, then every <img> gets lazy loading so a
 * long release note never blocks the detail page.
 */
final class DescriptionHtml
{
    public static function render(?string $raw): string
    {
        return self::lazyImages(str($raw ?? '')->sanitizeHtml()->toString());
    }

    /**
     * Add `loading`/`decoding`/`referrerpolicy` to images that do not set them.
     */
    public static function lazyImages(string $html): string
    {
        if (! str_contains(strtolower($html), '<img')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<img\b[^>]*>/i',
            static function (array $matches): string {
                $tag = $matches[0];

                foreach ([
                    'loading' => 'lazy',
                    'decoding' => 'async',
                    'referrerpolicy' => 'no-referrer',
                ] as $attribute => $value) {
                    if (preg_match('/\b'.$attribute.'\s*=/i', $tag) === 1) {
                        continue;
                    }

                    $tag = (string) preg_replace(
                        '/^<img\b/i',
                        '<img '.$attribute.'="'.$value.'"',
                        $tag,
                        1,
                    );
                }

                return $tag;
            },
            $html,
        );
    }
}
