<?php

namespace App\Http\Requests\Auth;

use App\Support\Turnstile;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...Turnstile::validationRules(Turnstile::FEATURE_LOGIN),
        ];
    }

    protected function passedValidation(): void
    {
        Turnstile::validateRequest(Turnstile::FEATURE_LOGIN);
    }
}
