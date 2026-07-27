<?php

namespace App\Models;

use App\DocStatus;
use Database\Factories\DocFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'slug',
    'category',
    'excerpt',
    'cover_path',
    'body',
    'status',
    'published_at',
    'sort_order',
])]
class Doc extends Model
{
    /** @use HasFactory<DocFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Doc $doc): void {
            if (
                $doc->status === DocStatus::Published
                && blank($doc->published_at)
            ) {
                $doc->published_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => DocStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<Doc>  $query
     * @return Builder<Doc>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', DocStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
