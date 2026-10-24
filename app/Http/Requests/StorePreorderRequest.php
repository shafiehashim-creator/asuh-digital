<?php

namespace App\Http\Requests;

use App\Enums\AgeGroup;
use App\Enums\PaymentMethod;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePreorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
        }
    }

    /**
     * Guests also fill in their new account; all errors are shown in one go.
     * CreateNewUser validates the account fields again when it creates the user.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->user() ? [] : [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];

        return $account + [
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
            'state' => ['required', Rule::in(config('asuh.states'))],
            'children_count' => ['required', 'integer', 'min:1', 'max:10'],
            'children_age_group' => ['required', Rule::enum(AgeGroup::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'pdpa_consent' => ['accepted'],
        ];
    }
}
