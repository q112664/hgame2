<?php

namespace App\Support;

use App\Models\Tag;
use Illuminate\Support\Str;

class TagImporter
{
    /**
     * @return list<int>
     */
    public function import(string $names): array
    {
        return array_values(collect(preg_split('/[,，\s]+/u', $names) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(fn (string $name): string => (string) preg_replace('/\s+/u', ' ', $name))
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(fn (string $name): Tag => $this->resolve($name))
            ->unique(fn (Tag $tag): int => $tag->id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    public function importNames(array $names): array
    {
        return array_values(collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(fn (string $name): string => (string) preg_replace('/\s+/u', ' ', $name))
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(fn (string $name): Tag => $this->resolve($name))
            ->unique(fn (Tag $tag): int => $tag->id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }

    protected function resolve(string $name): Tag
    {
        $slug = $this->slugFor($name);

        $existing = Tag::query()
            ->where(function ($query) use ($slug, $name): void {
                $query->where('slug', $slug)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($name)]);
            })
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Tag::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    protected function slugFor(string $name): string
    {
        return Str::slug($name, language: null) ?: 'tag-'.substr(md5(Str::lower($name)), 0, 8);
    }
}
