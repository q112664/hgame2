<?php

namespace App\Models;

use App\DocStatus;
use Database\Factories\DocFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'slug',
    'category',
    'excerpt',
    'body',
    'status',
    'published_at',
    'reading_minutes',
    'sort_order',
])]
class Doc extends Model
{
    /** @use HasFactory<DocFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Doc $doc): void {
            if ($doc->isDirty('body') && filled($doc->body)) {
                $doc->body = self::ensureHeadingIds((string) $doc->body);
            }

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
            'reading_minutes' => 'integer',
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

    public function readingMinutes(): int
    {
        if ($this->reading_minutes !== null && $this->reading_minutes > 0) {
            return $this->reading_minutes;
        }

        $words = str_word_count(strip_tags((string) $this->body));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public function headings(): array
    {
        if (! preg_match_all(
            '/<h2\b([^>]*)>(.*?)<\/h2>/is',
            (string) $this->body,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $headings = [];

        foreach ($matches as $match) {
            $title = trim(html_entity_decode(strip_tags($match[2])));

            if ($title === '') {
                continue;
            }

            $id = null;

            if (preg_match('/\bid\s*=\s*([\'"])(.*?)\1/i', $match[1], $idMatch) === 1) {
                $id = $idMatch[2];
            }

            $headings[] = [
                'id' => $id !== null && $id !== '' ? $id : Str::slug($title),
                'title' => $title,
            ];
        }

        return $headings;
    }

    public static function ensureHeadingIds(string $html): string
    {
        return (string) preg_replace_callback(
            '/<h2\b([^>]*)>(.*?)<\/h2>/is',
            static function (array $match): string {
                $attributes = $match[1];
                $inner = $match[2];
                $title = trim(html_entity_decode(strip_tags($inner)));

                if ($title === '') {
                    return $match[0];
                }

                if (preg_match('/\bid\s*=/i', $attributes) === 1) {
                    return $match[0];
                }

                $id = Str::slug($title);

                return '<h2 id="'.e($id).'"'.$attributes.'>'.$inner.'</h2>';
            },
            $html,
        );
    }
}
