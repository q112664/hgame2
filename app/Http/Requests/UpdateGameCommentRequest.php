<?php

namespace App\Http\Requests;

use App\Models\GameComment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGameCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        if (! $comment instanceof GameComment) {
            return false;
        }

        $user = $this->user();

        return $user !== null && $user->id === $comment->user_id;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $comment = $this->route('comment');

            if (! $comment instanceof GameComment) {
                return;
            }

            if ($comment->parent_id !== null && $this->input('rating') !== null) {
                $validator->errors()->add('rating', 'Replies cannot include a rating.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');
        $rating = $this->input('rating');
        $merged = [];

        if (is_string($body)) {
            $plain = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body) ?? $body;
            $plain = preg_replace('/<[^>]*>/', '', $plain) ?? $plain;
            $merged['body'] = trim(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if ($rating === '' || $rating === '0') {
            $merged['rating'] = null;
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
