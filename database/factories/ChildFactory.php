<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Enums\ChildAvatar;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nickname' => fake()->randomElement(['Adik', 'Along', 'Angah', 'Achik', 'Usu']),
            'age_group' => fake()->randomElement([AgeGroup::SixToNine, AgeGroup::TenToThirteen]),
            'avatar' => fake()->randomElement(ChildAvatar::cases()),
        ];
    }
}
