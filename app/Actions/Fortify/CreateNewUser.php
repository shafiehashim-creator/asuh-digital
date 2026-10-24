<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Creates a parent account. Used by pre-order checkout, since the
 * standalone registration page is disabled.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $input['phone'] = Phone::normalize($input['phone'] ?? null);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
            'password' => $this->passwordRules(),
            'pdpa_consent' => ['accepted'],
        ])->validate();

        // Role is not mass assignable; new accounts get the "parent" column default.
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);
    }
}
