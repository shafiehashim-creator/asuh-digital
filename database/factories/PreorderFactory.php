<?php

namespace Database\Factories;

use App\Enums\AgeGroup;
use App\Enums\PreorderStatus;
use App\Models\Preorder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preorder>
 */
class PreorderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '01'.fake()->numerify('########'),
            'state' => fake()->randomElement(config('asuh.states')),
            'children_count' => fake()->numberBetween(1, 4),
            'children_age_group' => fake()->randomElement(AgeGroup::cases()),
            'pdpa_consented_at' => now(),
            'status' => PreorderStatus::Reserved,
            'reserved_until' => now()->addMinutes(30),
        ];
    }

    public function status(PreorderStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function expiredReservation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreorderStatus::Reserved,
            'reserved_until' => now()->subMinute(),
        ]);
    }
}
