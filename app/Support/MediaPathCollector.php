<?php

namespace App\Support;

use App\Models\Doc;
use App\Models\Game;
use App\Models\GameRelease;
use App\Models\GameScreenshot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class MediaPathCollector
{
    /** @return list<string> */
    public function all(string $disk): array
    {
        $paths = collect($this->references());

        try {
            $paths = $paths->merge(Storage::disk($disk)->allFiles());
        } catch (Throwable) {
            // Database references are still useful when a disk cannot be listed.
        }

        return $this->normalize($paths->all());
    }

    /** @return list<string> */
    public function references(): array
    {
        $paths = collect();

        Game::query()
            ->whereNotNull('cover_path')
            ->pluck('cover_path')
            ->each(fn (mixed $path) => $paths->push($path));

        GameScreenshot::query()
            ->whereNotNull('path')
            ->pluck('path')
            ->each(fn (mixed $path) => $paths->push($path));

        Doc::query()
            ->whereNotNull('cover_path')
            ->pluck('cover_path')
            ->each(fn (mixed $path) => $paths->push($path));

        User::query()
            ->whereNotNull('avatar')
            ->pluck('avatar')
            ->each(fn (mixed $path) => $paths->push($path));

        foreach (['site_favicon_path', 'site_logo_path', 'hero_background_path', 'seo_og_image_path'] as $key) {
            $paths->push(Setting::get($key));
        }

        Game::query()
            ->whereNotNull('description')
            ->pluck('description')
            ->each(fn (mixed $html) => $paths->push(...$this->fromHtml((string) $html)));

        GameRelease::query()
            ->whereNotNull('description')
            ->pluck('description')
            ->each(fn (mixed $html) => $paths->push(...$this->fromHtml((string) $html)));

        Doc::query()
            ->whereNotNull('body')
            ->pluck('body')
            ->each(fn (mixed $html) => $paths->push(...$this->fromHtml((string) $html)));

        $paths->push(...$this->fromHtml((string) (Setting::get('resource_notice_content') ?? '')));

        return $this->normalize($paths->all());
    }

    /** @return list<string> */
    public function optimizable(string $disk): array
    {
        return array_values(array_filter(
            $this->references(),
            static function (string $path) use ($disk): bool {
                if (
                    str_contains($path, '/thumbs/')
                    || str_starts_with($path, 'site/favicon/')
                    || str_starts_with($path, 'site/seo/')
                ) {
                    return false;
                }

                return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)
                    && Storage::disk($disk)->exists($path);
            },
        ));
    }

    public function isReferenced(string $path): bool
    {
        if (
            Game::query()->where('cover_path', $path)->exists()
            || GameScreenshot::query()->where('path', $path)->exists()
            || Doc::query()->where('cover_path', $path)->exists()
            || User::query()->where('avatar', $path)->exists()
        ) {
            return true;
        }

        foreach (['site_favicon_path', 'site_logo_path', 'hero_background_path', 'seo_og_image_path'] as $key) {
            if (Setting::get($key) === $path) {
                return true;
            }
        }

        $references = [
            $path,
            '/storage/'.$path,
            rawurlencode($path),
        ];

        foreach ($references as $reference) {
            if (
                Game::query()->whereLike('description', '%'.$reference.'%')->exists()
                || GameRelease::query()->whereLike('description', '%'.$reference.'%')->exists()
                || Doc::query()->whereLike('body', '%'.$reference.'%')->exists()
            ) {
                return true;
            }
        }

        $notice = (string) (Setting::get('resource_notice_content') ?? '');

        return collect($references)->contains(fn (string $reference): bool => str_contains($notice, $reference));
    }

    /** @return list<string> */
    private function fromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all(
            '~(?:^|["\'=\s(])(?:/storage/|https?://[^"\'>\s)]+/)((?:avatars|docs|games|site)/[^"\'>\s?#)]+)~i',
            $html,
            $matches,
        );

        return array_map(
            static fn (string $path): string => rawurldecode(ltrim($path, '/')),
            $matches[1],
        );
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return list<string>
     */
    private function normalize(array $paths): array
    {
        return array_values(collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->map(fn (string $path): ?string => $this->managedPath($path))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all());
    }

    private function managedPath(string $path): ?string
    {
        $path = html_entity_decode(trim($path), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::after($path, '/storage/'), '/');
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $urlPath = parse_url($path, PHP_URL_PATH);

            if (! is_string($urlPath)) {
                return null;
            }

            if (str_starts_with($urlPath, '/storage/')) {
                return ltrim(Str::after($urlPath, '/storage/'), '/');
            }

            $candidate = ltrim($urlPath, '/');

            return preg_match('#^(?:avatars|docs|games|site)/#i', $candidate) === 1
                ? rawurldecode($candidate)
                : null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        return preg_match('#^(?:avatars|docs|games|site)/#i', $path) === 1
            ? $path
            : null;
    }
}
