<?php

namespace App\Http\Requests\Api\V1;

use App\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
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
        /** @var Game $game */
        $game = $this->route('game');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('games', 'slug')->ignore($game->id),
            ],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'developer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'release_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'detail_versions' => ['sometimes', 'nullable', 'array', 'max:20'],
            'detail_versions.*.language' => ['required', 'string', 'max:255'],
            'detail_versions.*.description' => ['nullable', 'string'],
            'detail_versions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'cover_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'status' => ['sometimes', 'nullable', Rule::enum(GameStatus::class)],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'screenshots' => ['sometimes', 'nullable', 'array', 'max:50'],
            'screenshots.*' => ['url', 'max:2048'],
            'releases' => ['sometimes', 'nullable', 'array'],
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
            'releases.*.download_links' => ['required', 'array', 'min:1'],
            'releases.*.download_links.*' => ['url', 'max:2048'],
        ];
    }
}
