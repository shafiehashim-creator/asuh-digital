<?php

namespace Database\Factories;

use App\Enums\LicenseKind;
use App\Models\Institution;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'kind' => LicenseKind::Paid,
            'max_students' => 30,
            'starts_on' => today()->subMonth(),
            'ends_on' => today()->addMonths(11),
        ];
    }
}
