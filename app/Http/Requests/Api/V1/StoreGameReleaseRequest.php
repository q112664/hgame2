<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user?->is_admin;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'max:255'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'contributor' => ['nullable', 'email', 'max:255'],
            'download_links' => ['required', 'array', 'min:1'],
            'download_links.*' => ['url', 'max:2048'],
            'touch_downloads' => ['sometimes', 'boolean'],
        ];
    }
}
