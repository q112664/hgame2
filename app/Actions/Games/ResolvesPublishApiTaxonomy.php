<?php

namespace App\Actions\Games;

use App\Models\Language;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ResolvesPublishApiTaxonomy
{
    protected function resolveContributorId(?string $email, string $errorKey): ?int
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower(trim($email))])
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                $errorKey => "Unknown contributor email [{$email}].",
            ]);
        }

        return $user->id;
    }

    protected function resolvePlatform(string $value, string $errorKey = 'releases'): Platform
    {
        $platform = Platform::query()
            ->where(function ($query) use ($value): void {
                $query->where('slug', $value)
                    ->orWhereRaw('lower(name) = ?', [Str::lower($value)]);
            })
            ->first();

        if ($platform === null) {
            throw ValidationException::withMessages([
                $errorKey => "Unknown platform [{$value}].",
            ]);
        }

        return $platform;
    }

    protected function resolveLanguage(string $value, string $errorKey = 'releases'): Language
    {
        $language = Language::query()
            ->where(function ($query) use ($value): void {
                $query->whereRaw('lower(code) = ?', [Str::lower($value)])
                    ->orWhereRaw('lower(name) = ?', [Str::lower($value)]);
            })
            ->first();

        if ($language === null) {
            throw ValidationException::withMessages([
                $errorKey => "Unknown language [{$value}].",
            ]);
        }

        return $language;
    }
}
