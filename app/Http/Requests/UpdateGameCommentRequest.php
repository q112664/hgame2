<?php

namespace App\Http\Requests;

use App\Models\GameComment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if (! is_string($body)) {
            return;
        }

        $plain = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body) ?? $body;
        $plain = preg_replace('/<[^>]*>/', '', $plain) ?? $plain;

        $this->merge([
            'body' => trim(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ]);
    }
}
