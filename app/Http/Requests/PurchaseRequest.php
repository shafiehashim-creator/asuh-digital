<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Institution;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Regular pass and single book purchases. Guests create their account here
 * (all errors shown at once); CreateNewUser validates the account again.
 */
class PurchaseRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->user() ? [] : [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'pdpa_consent' => ['accepted'],
        ];

        return $account + [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only(PaymentMethod::forCheckout())],
            // School parent discount code (yearly subscriptions only; ignored elsewhere).
            'school_code' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! Institution::findByParentCode($value)) {
                    $fail('Kod sekolah tidak sah. Semak semula dengan guru atau sekolah anak anda.');
                }
            }],
        ];
    }
}
