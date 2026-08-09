<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code'])]
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    /** @return BelongsToMany<GameRelease, $this> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(GameRelease::class);
    }

    /** @return HasMany<GameDetailTranslation, $this> */
    public function detailTranslations(): HasMany
    {
        return $this->hasMany(GameDetailTranslation::class);
    }
}
