<?php

namespace App\Http\Requests;

use App\Models\Game;
use App\Models\GameComment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGameCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:game_comments,id'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->input('parent_id');
            $rating = $this->input('rating');

            if ($parentId !== null && $parentId !== '' && $rating !== null) {
                $validator->errors()->add('rating', 'Replies cannot include a rating.');
            }

            if ($parentId === null || $parentId === '') {
                return;
            }

            $resource = $this->route('resource');

            if (! $resource instanceof Game) {
                return;
            }

            $parent = GameComment::query()->whereKey($parentId)->first();

            if ($parent === null || $parent->game_id !== $resource->id) {
                $validator->errors()->add('parent_id', 'The reply target is invalid.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');
        $parentId = $this->input('parent_id');
        $rating = $this->input('rating');

        $merged = [];

        if (is_string($body)) {
            $plain = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body) ?? $body;
            $plain = preg_replace('/<[^>]*>/', '', $plain) ?? $plain;
            $merged['body'] = trim(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if ($parentId === '' || $parentId === '0') {
            $merged['parent_id'] = null;
        }

        if ($rating === '' || $rating === '0') {
            $merged['rating'] = null;
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
