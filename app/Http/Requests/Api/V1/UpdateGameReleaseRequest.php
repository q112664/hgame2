<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameReleaseRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'platforms' => ['sometimes', 'required', 'array', 'min:1'],
            'platforms.*' => ['string', 'max:255'],
            'languages' => ['sometimes', 'required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:255'],
            'version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file_size' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'contributor' => ['sometimes', 'nullable', 'email', 'max:255'],
            'download_links' => ['sometimes', 'required', 'array', 'min:1'],
            'download_links.*' => ['url', 'max:2048'],
            'touch_downloads' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $fields = [
                    'title',
                    'platforms',
                    'languages',
                    'version',
                    'file_size',
                    'description',
                    'is_active',
                    'published_at',
                    'contributor',
                    'download_links',
                    'touch_downloads',
                ];

                foreach ($fields as $field) {
                    if ($this->exists($field)) {
                        return;
                    }
                }

                $validator->errors()->add('title', 'Provide at least one release field to update.');
            },
        ];
    }
}
