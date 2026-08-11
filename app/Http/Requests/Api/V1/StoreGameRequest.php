<?php

namespace App\Http\Requests\Api\V1;

use App\GameStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRequest extends FormRequest
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
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('games', 'slug')],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_id' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'release_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'detail_versions' => ['nullable', 'array', 'max:20'],
            'detail_versions.*.language' => ['required', 'string', 'max:255'],
            'detail_versions.*.description' => ['nullable', 'string'],
            'detail_versions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'cover_url' => ['required', 'url', 'max:2048'],
            'status' => ['nullable', Rule::enum(GameStatus::class)],
            'published_at' => ['nullable', 'date'],
            'screenshots' => ['nullable', 'array', 'max:50'],
            'screenshots.*' => ['url', 'max:2048'],
            'releases' => ['nullable', 'array'],
            'releases.*.title' => ['required', 'string', 'max:255'],
            'releases.*.platforms' => ['required', 'array', 'min:1'],
            'releases.*.platforms.*' => ['string', 'max:255'],
            'releases.*.languages' => ['required', 'array', 'min:1'],
            'releases.*.languages.*' => ['string', 'max:255'],
            'releases.*.version' => ['nullable', 'string', 'max:255'],
            'releases.*.file_size' => ['nullable', 'string', 'max:255'],
            'releases.*.description' => ['nullable', 'string'],
            'releases.*.is_active' => ['nullable', 'boolean'],
            'releases.*.published_at' => ['nullable', 'date'],
            'releases.*.contributor' => ['nullable', 'email', 'max:255'],
            'releases.*.download_links' => ['required', 'array', 'min:1'],
            'releases.*.download_links.*' => ['url', 'max:2048'],
        ];
    }
}
