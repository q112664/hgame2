<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DescriptionMediaImporter
{
    public const int MaxImages = 30;

    public const string Directory = 'games/content';

    public function __construct(
        private RemoteMediaDownloader $mediaDownloader,
    ) {}

    /**
     * Download remote images embedded in HTML and rewrite their src attributes
     * to local media URLs (same shape as Filament RichEditor attachments).
     *
     * @return array{html: string|null, paths: list<string>}
     */
    public function import(?string $html, string $errorKey = 'description'): array
    {
        if ($html === null || $html === '') {
            return ['html' => $html, 'paths' => []];
        }

        if (! preg_match('/<img\b/i', $html)) {
            return ['html' => $html, 'paths' => []];
        }

        [$dom, $root] = $this->loadHtmlDocument($html, $errorKey);

        /** @var list<DOMElement> $images */
        $images = [];

        foreach ($root->getElementsByTagName('img') as $image) {
            $images[] = $image;
        }

        /** @var array<string, string> $cache */
        $cache = [];
        /** @var list<string> $paths */
        $paths = [];

        foreach ($images as $image) {
            $src = html_entity_decode(trim($image->getAttribute('src')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($src === '' || $this->isAlreadyLocal($src)) {
                continue;
            }

            if (Str::startsWith(Str::lower($src), 'data:')) {
                throw ValidationException::withMessages([
                    $errorKey => 'Description images must be remote http(s) URLs, not data URIs.',
                ]);
            }

            if (! $this->isRemoteHttpUrl($src)) {
                continue;
            }

            if (! isset($cache[$src])) {
                if (count($cache) >= self::MaxImages) {
                    throw ValidationException::withMessages([
                        $errorKey => 'Description may contain at most '.self::MaxImages.' remote images.',
                    ]);
                }

                try {
                    $path = $this->mediaDownloader->download($src, self::Directory);
                } catch (ValidationException $exception) {
                    $message = $exception->errors()['media'][0]
                        ?? "Failed to download description image from [{$src}].";

                    throw ValidationException::withMessages([
                        $errorKey => $message,
                    ]);
                }

                $paths[] = $path;
                $cache[$src] = $this->publicSrcFor($path);
            }

            $image->setAttribute('src', $cache[$src]);
        }

        return [
            'html' => $this->innerHtml($root),
            'paths' => $paths,
        ];
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private function loadHtmlDocument(string $html, string $errorKey): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8"><div id="description-media-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } catch (Throwable) {
            $loaded = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $loaded ? $dom->getElementById('description-media-root') : null;

        if (! $root instanceof DOMElement) {
            throw ValidationException::withMessages([
                $errorKey => 'Description HTML could not be parsed.',
            ]);
        }

        return [$dom, $root];
    }

    private function innerHtml(DOMElement $root): string
    {
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }

    private function isAlreadyLocal(string $src): bool
    {
        if (preg_match('#^games/(?:covers|screenshots|content)/#i', $src) === 1) {
            return true;
        }

        return preg_match(
            '#(?:^/storage/|https?://[^"\'>\s]+/)(games/(?:covers|screenshots|content)/)#i',
            $src,
        ) === 1;
    }

    private function isRemoteHttpUrl(string $src): bool
    {
        return Str::startsWith(Str::lower($src), ['http://', 'https://']);
    }

    private function publicSrcFor(string $path): string
    {
        if (Media::diskName() === 'public') {
            return '/storage/'.$path;
        }

        return Media::url($path);
    }
}
