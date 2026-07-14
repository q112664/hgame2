<?php

namespace App\Http\Requests;

use App\Actions\Games\ListPublishedGames;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:100', Rule::exists('categories', 'slug')],
            'platform' => ['nullable', 'string', 'max:100', Rule::exists('platforms', 'slug')],
            'language' => ['nullable', 'string', 'max:20', Rule::exists('languages', 'code')],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:100', Rule::exists('tags', 'slug')],
            'sort' => ['nullable', 'string', Rule::in(ListPublishedGames::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_string($tags)) {
            $tags = preg_split('/[,\s]+/', $tags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($tags)) {
            $tags = [];
        }

        $this->merge([
            'category' => $this->filled('category') ? $this->string('category')->toString() : null,
            'platform' => $this->filled('platform') ? $this->string('platform')->toString() : null,
            'language' => $this->filled('language') ? $this->string('language')->toString() : null,
            'tags' => array_values(array_unique(array_filter(
                array_map(fn (mixed $tag): string => is_string($tag) ? trim($tag) : '', $tags),
            ))),
            'sort' => $this->filled('sort')
                ? $this->string('sort')->toString()
                : ListPublishedGames::SORT_LATEST,
        ]);
    }

    /**
     * @return array{category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string}
     */
    public function filters(): array
    {
        /** @var array{category?: string|null, platform?: string|null, language?: string|null, tags?: list<string>, sort?: string} $validated */
        $validated = $this->validated();

        return [
            'category' => $validated['category'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'language' => $validated['language'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'sort' => $validated['sort'] ?? ListPublishedGames::SORT_LATEST,
        ];
    }
}
