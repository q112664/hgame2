<?php

namespace App\Actions\ResourceSources;

use App\Models\ResourceSource;
use App\Support\Media;
use App\Support\RemoteMediaDownloader;
use Illuminate\Support\Str;

class UpsertResourceSource
{
    public function __construct(
        private readonly RemoteMediaDownloader $mediaDownloader,
    ) {}

    /**
     * Create or update a reusable storefront source (name + optional icon).
     *
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     host_hint?: string|null,
     *     icon_path?: string|null,
     *     icon_url?: string|null,
     *     sort_order?: int|null
     * }  $data
     */
    public function __invoke(array $data): ResourceSource
    {
        $name = trim((string) $data['name']);
        $slugInput = isset($data['slug']) ? trim((string) $data['slug']) : '';
        $hostHint = isset($data['host_hint']) ? trim((string) $data['host_hint']) : null;
        $hostHint = $hostHint !== '' ? $hostHint : null;

        $source = ResourceSource::query()
            ->where(function ($query) use ($name, $slugInput): void {
                $query->where('name', $name);

                if ($slugInput !== '') {
                    $query->orWhere('slug', Str::slug($slugInput));
                }
            })
            ->first();

        $slug = $slugInput !== ''
            ? Str::slug($slugInput)
            : ($source?->slug ?: $this->uniqueSlug(Str::slug($name) ?: 'source'));

        if ($source === null) {
            $source = new ResourceSource([
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $data['sort_order']
                    ?? (((int) ResourceSource::query()->max('sort_order')) + 1),
            ]);
        } else {
            $source->name = $name;

            if ($slugInput !== '' && $slug !== $source->slug) {
                $source->slug = $this->uniqueSlug($slug, $source->getKey());
            }
        }

        if (array_key_exists('host_hint', $data)) {
            $source->host_hint = $hostHint;
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $source->sort_order = (int) $data['sort_order'];
        }

        $nextIcon = $this->resolveIconPath(
            $data['icon_path'] ?? null,
            $data['icon_url'] ?? null,
        );

        if ($nextIcon !== null) {
            $previous = $source->icon_path;
            $source->icon_path = $nextIcon;

            if (
                filled($previous)
                && $previous !== $nextIcon
                && ! Str::startsWith((string) $previous, ['http://', 'https://', '/'])
            ) {
                Media::delete($previous);
            }
        }

        $source->save();

        return $source->refresh();
    }

    private function resolveIconPath(mixed $iconPath, mixed $iconUrl): ?string
    {
        if (is_string($iconPath) && trim($iconPath) !== '') {
            return trim($iconPath);
        }

        if (! is_string($iconUrl) || trim($iconUrl) === '') {
            return null;
        }

        $iconUrl = trim($iconUrl);

        // Allow pointing at already-public assets without re-download.
        if (Str::startsWith($iconUrl, ['/images/', '/storage/'])) {
            return $iconUrl;
        }

        return $this->mediaDownloader->download($iconUrl, 'site/sources');
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'source';
        $candidate = $slug;
        $suffix = 2;

        while (
            ResourceSource::query()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
