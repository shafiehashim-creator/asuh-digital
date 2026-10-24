<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Enums\InstitutionType;
use App\Enums\PilotStatus;
use App\Models\PilotRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotRequest>
 */
class PilotRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_name' => 'Tadika '.fake()->lastName(),
            'institution_type' => fake()->randomElement(InstitutionType::cases()),
            'state' => fake()->randomElement(config('asuh.states')),
            'district' => fake()->city(),
            'contact_name' => fake()->name(),
            'contact_position' => 'Guru Besar',
            'email' => fake()->safeEmail(),
            'phone' => '01'.fake()->numerify('########'),
            'student_count' => fake()->numberBetween(20, 300),
            'age_group' => fake()->randomElement(AgeGroup::cases()),
            'status' => PilotStatus::New,
        ];
    }
}
