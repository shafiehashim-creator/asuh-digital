<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Models\Classroom;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => 'Kelas '.fake()->randomElement(['Bestari', 'Cemerlang', 'Gemilang', 'Al-Farabi']),
            'age_group' => AgeGroup::SixToNine,
        ];
    }
}
