<?php

namespace App\Http\Requests;

use App\Enums\AgeGroup;
use App\Enums\ChildAvatar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:30'],
            // A child is in one group; "both" is for books only.
            'age_group' => ['required', Rule::in([AgeGroup::SixToNine->value, AgeGroup::TenToThirteen->value])],
            'avatar' => ['required', Rule::enum(ChildAvatar::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nickname' => 'nama panggilan',
            'age_group' => 'kumpulan umur',
            'avatar' => 'avatar',
        ];
    }
}
