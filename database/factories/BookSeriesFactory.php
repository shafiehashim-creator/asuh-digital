<?php

namespace Database\Factories;

use App\Models\BookSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookSeries> */
class BookSeriesFactory extends Factory
{
    protected $model = BookSeries::class;

    public function definition(): array
    {
        return ['name' => 'Siri '.fake()->unique()->words(2, true)];
    }
}
