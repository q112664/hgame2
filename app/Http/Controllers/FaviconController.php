<?php

namespace App\Http\Controllers;

use App\Support\FaviconIcons;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the classic icon paths from the favicon uploaded in the admin.
 *
 * `/favicon.ico` is answered by nginx from `public/` (see FaviconIcons::publish()),
 * these routes cover the PNG endpoints and the apple-touch icon, and act as a fallback
 * wherever the static files are missing.
 */
final class FaviconController
{
    public function __construct(private readonly FaviconIcons $icons) {}

    public function ico(): Response
    {
        return $this->respond($this->icons->ico(), 'image/x-icon');
    }

    public function png(int $size): Response
    {
        return $this->respond($this->icons->png($size), 'image/png');
    }

    public function appleTouch(): Response
    {
        return $this->respond($this->icons->appleTouch(), 'image/png');
    }

    private function respond(?string $body, string $contentType): Response
    {
        if ($body === null || $body === '') {
            abort(404);
        }

        $response = new Response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.substr(sha1($body), 0, 16).'"',
        ]);

        $response->setLastModified(now()->subMinute());
        $response->isNotModified(request());

        return $response;
    }
}
