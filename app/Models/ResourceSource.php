<?php

namespace App\Models;

use App\Support\GameSource;
use App\Support\Media;
use Database\Factories\ResourceSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'icon_path', 'host_hint', 'sort_order'])]
class ResourceSource extends Model
{
    /** @use HasFactory<ResourceSourceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (): void {
            GameSource::forgetCache();
        });

        static::deleted(function (): void {
            GameSource::forgetCache();
        });

        static::deleting(function (ResourceSource $source): void {
            $source->deleteManagedIcon();
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function iconUrl(): ?string
    {
        if (blank($this->icon_path)) {
            return null;
        }

        $url = Media::url($this->icon_path);

        return $url !== '' ? $url : null;
    }

    /**
     * @param  Builder<ResourceSource>  $query
     * @return Builder<ResourceSource>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function deleteManagedIcon(): void
    {
        if (blank($this->icon_path)) {
            return;
        }

        // Seeded public assets under /images must never be deleted from disk.
        if (Str::startsWith($this->icon_path, ['http://', 'https://', '/'])) {
            return;
        }

        Media::delete($this->icon_path);
    }
}
