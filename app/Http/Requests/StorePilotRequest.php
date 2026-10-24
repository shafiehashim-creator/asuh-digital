<?php

namespace App\Http\Requests;

use App\Enums\AgeGroup;
use App\Enums\InstitutionType;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePilotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_type' => ['required', Rule::enum(InstitutionType::class)],
            'state' => ['required', Rule::in(config('asuh.states'))],
            'district' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_position' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.Phone::ANY_PATTERN],
            'student_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'age_group' => ['required', Rule::enum(AgeGroup::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pdpa_consent' => ['accepted'],
            // Honeypot: hidden from people, often filled in by spam bots.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Sila masukkan no. telefon Malaysia yang sah, contohnya 012-345 6789 atau 03-6123 4567.',
            'pdpa_consent.accepted' => 'Anda perlu bersetuju untuk kami menghubungi anda tentang program pilot.',
        ];
    }
}
