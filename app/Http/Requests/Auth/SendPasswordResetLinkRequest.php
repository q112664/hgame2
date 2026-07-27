<?php

namespace App\Http\Requests\Auth;

use App\Support\Turnstile;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest as FortifySendPasswordResetLinkRequest;

class SendPasswordResetLinkRequest extends FortifySendPasswordResetLinkRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...Turnstile::validationRules(Turnstile::FEATURE_FORGOT_PASSWORD),
        ];
    }

    protected function passedValidation(): void
    {
        Turnstile::validateRequest(Turnstile::FEATURE_FORGOT_PASSWORD);
    }
}
