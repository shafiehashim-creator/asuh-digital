<?php

namespace Database\Factories;

use App\Models\Contributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contributor> */
class ContributorFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name()];
    }
}
