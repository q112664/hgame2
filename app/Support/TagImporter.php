<?php

namespace App\Support;

use App\Models\Tag;
use Illuminate\Support\Str;

class TagImporter
{
    public function import(string $names): int
    {
        return collect(preg_split('/[,\r\n]+/', $names) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->map(fn (string $name): Tag => Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            ))
            ->count();
    }
}
