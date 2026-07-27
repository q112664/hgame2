<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            ...Turnstile::validationRules(Turnstile::FEATURE_REGISTER),
        ])->validate();

        Turnstile::validateRequest(
            Turnstile::FEATURE_REGISTER,
            isset($input[Turnstile::FIELD]) ? (string) $input[Turnstile::FIELD] : null,
        );

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'registration_ip' => request()->ip(),
        ]);
    }
}
