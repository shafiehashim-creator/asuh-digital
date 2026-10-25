<?php

namespace Database\Factories;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Tadika '.fake()->lastName(),
            'type' => InstitutionType::Kindergarten,
            'state' => 'Selangor',
            'district' => 'Gombak',
        ];
    }
}
